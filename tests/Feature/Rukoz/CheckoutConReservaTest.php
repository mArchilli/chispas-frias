<?php

namespace Tests\Feature\Rukoz;

use App\Models\Addon;
use App\Models\CardPaymentPlan;
use App\Models\Category;
use App\Models\Combo;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Rukoz\CheckoutCentral;
use App\Rukoz\EspejoStock;
use App\Rukoz\PedidoParaCentral;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Rukoz\CoreClient\Circuito\Circuito;
use Rukoz\CoreClient\Compensaciones\Compensacion;
use Rukoz\CoreClient\Eventos\StockActualizado;
use Rukoz\CoreClient\Stock\StockItem;
use RuntimeException;
use Tests\Support\CentralDePrueba;
use Tests\TestCase;

/**
 * 10.3 — El checkout reserva en el Central antes de guardar el pedido. Habla con el cliente REAL del paquete (idempotencia,
 * circuito, compensaciones) contra un Central falso con estado que valida cada body con el contrato 0.1.2 (tests/Support).
 */
class CheckoutConReservaTest extends TestCase
{
    use RefreshDatabase;

    private CentralDePrueba $central;

    protected function setUp(): void
    {
        parent::setUp();

        $this->central = CentralDePrueba::instalar();

        // En producción lo registra AppServiceProvider al arrancar con INTEGRACION_STOCK; acá la config se enciende después del arranque.
        Event::listen(StockActualizado::class, [EspejoStock::class, 'alActualizarse']);
    }

    protected function tearDown(): void
    {
        // Nada de lo que la web mandó puede violar el contrato del Central.
        $this->assertSame([], $this->central->erroresDeContrato, 'La web mandó un body que el Central rechazaría');

        parent::tearDown();
    }

    /**
     * Manda a propósito TODOS los datos (también los que el checkout mínimo ya no pide): acá se prueba cómo viajan al Central
     * (`cliente`). El pedido con lo mínimo está en CheckoutMinimoTest.
     */
    private function datosCliente(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Juana',
            'lastname' => 'Pérez',
            'dni' => '30111222',
            'province' => 'buenos-aires',
            'city' => 'La Plata',
            'postal_code' => '1900',
            'phone' => '1122334455',
            'email' => 'juana@example.com',
            'observations' => null,
        ], $overrides);
    }

    private function checkout(array $carrito, array $sesionExtra = [], array $datos = []): TestResponse
    {
        return $this->withSession(array_merge(['cart' => $carrito], $sesionExtra))
            ->postJson(route('cart.whatsapp'), ['customer_data' => $this->datosCliente($datos)]);
    }

    private function producto(array $attrs = []): Product
    {
        return Product::factory()->for(Category::factory())->create(array_merge([
            'price' => 1000, 'stock' => 50, 'is_active' => true,
        ], $attrs));
    }

    private function variante(Product $producto, string $nombre, float $recargo = 0, ?int $stock = null, bool $personalizada = false): ProductVariant
    {
        return ProductVariant::factory()->create([
            'product_id' => $producto->id, 'name' => $nombre, 'price_addon' => $recargo, 'stock' => $stock,
            'is_custom_color' => $personalizada, 'is_active' => true,
        ]);
    }

    private function codigo(array $attrs = []): DiscountCode
    {
        return DiscountCode::create(array_merge([
            'code' => 'DIEZ', 'percentage' => 10, 'min_purchase_amount' => null,
            'usage_limit' => null, 'usage_count' => 0, 'start_date' => null, 'end_date' => null,
            'is_active' => true,
        ], $attrs));
    }

    /** El único pedido que recibió el Central, tal cual lo mandó la web. */
    private function reservaUnica(): array
    {
        $this->assertCount(1, $this->central->pedidos, 'Tendría que haber una sola reserva en el Central');

        return array_values($this->central->pedidos)[0]['payload'];
    }

    /**
     * Combo fijo (dos productos sin variantes) y combo con elección (una pistola con colores + chispas fijas, como el mixto
     * del catálogo real).
     */
    private function combosDeEjemplo(): array
    {
        $a = $this->producto(['title' => 'Chispa A', 'price' => 100]);
        $b = $this->producto(['title' => 'Chispa B', 'price' => 100]);
        $fijo = Combo::create(['title' => 'Combo fijo', 'price' => 1500, 'is_free_shipping' => false, 'is_active' => true]);
        $fijo->items()->create(['product_id' => $a->id, 'quantity' => 2, 'sort_order' => 0]);
        $fijo->items()->create(['product_id' => $b->id, 'quantity' => 1, 'sort_order' => 1]);

        $pistola = $this->producto(['title' => 'Pistola', 'price' => 5000]);
        $roja = $this->variante($pistola, 'Roja', 0, 5);
        $azul = $this->variante($pistola, 'Azul', 0, 5);
        $otro = $this->variante($pistola, 'Otro color', 0, null, personalizada: true);
        $mixto = Combo::create(['title' => 'Combo mixto', 'price' => 8000, 'is_free_shipping' => true, 'is_active' => true]);
        $lineaPistola = $mixto->items()->create(['product_id' => $pistola->id, 'quantity' => 1, 'sort_order' => 0]);
        $lineaChispas = $mixto->items()->create(['product_id' => $a->id, 'quantity' => 2, 'sort_order' => 1]);

        return compact('a', 'b', 'fijo', 'pistola', 'roja', 'azul', 'otro', 'mixto', 'lineaPistola', 'lineaChispas');
    }

    // ------------------------------------------------------------------------------------------------------------------
    // Qué se reserva y qué se guarda
    // ------------------------------------------------------------------------------------------------------------------

    public function test_producto_simple_reserva_en_el_central_y_no_descuenta_stock_local(): void
    {
        $producto = $this->producto(['title' => 'Chispa simple', 'price' => 1000, 'stock' => 10]);

        $respuesta = $this->checkout([['product_id' => $producto->id, 'quantity' => 3]], datos: ['observations' => 'Tocar timbre']);

        $respuesta->assertOk()->assertJson(['success' => true, 'total' => 3000.0]);
        $pedido = Order::with('items')->findOrFail($respuesta->json('order_id'));

        $this->assertStringStartsWith('CHF-', $pedido->rukoz_ref);
        $this->assertSame('reservado', $pedido->rukoz_estado);
        $this->assertSame(1, $pedido->rukoz_version);
        $this->assertTrue($pedido->usaCentral());
        $this->assertTrue($pedido->tieneReservaAbierta());

        // La web no toca su stock: lo reservó el Central.
        $this->assertSame(10, $producto->fresh()->stock);
        $this->assertSame(0, StockMovement::count());
        $this->assertNull(session('cart'));

        $payload = $this->reservaUnica();
        $this->assertSame($pedido->rukoz_ref, $payload['external_ref']);
        $this->assertEquals([[
            'tipo' => 'producto', 'id' => (string) $producto->id, 'nombre' => 'Chispa simple', 'cantidad' => 3, 'precio_unitario' => 1000.0,
        ]], $payload['items']);
        $this->assertEquals(3000.0, $payload['total']);
        $this->assertSame('ARS', $payload['moneda']);
        $this->assertSame([
            'nombre' => 'Juana Pérez', 'telefono' => '1122334455', 'dni' => '30111222', 'email' => 'juana@example.com',
            'localidad' => 'La Plata', 'provincia' => 'Buenos Aires', 'cp' => '1900', 'observaciones' => 'Tocar timbre',
        ], $payload['cliente']);

        // La web tiene un solo punto: no se manda y el Central usa el predeterminado. Tampoco viaja el envío.
        $this->assertArrayNotHasKey('punto', $payload);
        $this->assertArrayNotHasKey('total_con_recargo', $payload);
        $this->assertSame($pedido->created_at->toIso8601String(), $payload['creado_at']);
    }

    public function test_producto_con_variantes_va_como_variante_con_el_precio_final_por_unidad(): void
    {
        $producto = $this->producto(['title' => 'Pote de humo', 'price' => 4000]);
        $verde = $this->variante($producto, 'Verde', 500, 9);

        $this->checkout([['product_id' => $producto->id, 'quantity' => 2, 'variant_id' => $verde->id]])->assertOk();

        $this->assertEquals([[
            'tipo' => 'variante', 'id' => (string) $verde->id, 'nombre' => 'Pote de humo (Verde)', 'cantidad' => 2, 'precio_unitario' => 4500.0,
        ]], $this->reservaUnica()['items']);

        // Un producto con variantes nunca viaja como `producto` (sería no_aplica en el Central): lo valida el contrato del Central falso.
        $this->assertSame(9, $verde->fresh()->stock);
    }

    public function test_variante_otro_color_viaja_con_la_nota_del_color_pedido(): void
    {
        $pistola = $this->producto(['title' => 'Pistola', 'price' => 100000]);
        $this->variante($pistola, 'Negra', 0, 1);
        $otro = $this->variante($pistola, 'Otro color', 15000, null, personalizada: true);

        $this->checkout([['product_id' => $pistola->id, 'quantity' => 1, 'variant_id' => $otro->id, 'custom_color_text' => 'Turquesa PRUEBA']])->assertOk();

        $this->assertEquals([[
            'tipo' => 'variante', 'id' => (string) $otro->id, 'nombre' => 'Pistola (Otro color)', 'cantidad' => 1,
            'precio_unitario' => 115000.0, 'nota' => 'Color: Turquesa PRUEBA',
        ]], $this->reservaUnica()['items']);
    }

    public function test_add_on_con_texto_y_color_a_eleccion_viajan_en_la_nota_separados_por_punto_medio(): void
    {
        $producto = $this->producto(['title' => 'Bengala', 'price' => 500]);
        $libre = $this->variante($producto, 'Otro color', 0, null, personalizada: true);
        $grabado = Addon::factory()->conTexto()->create(['name' => 'Grabado', 'price' => 300]);
        $sinTexto = Addon::factory()->create(['name' => 'Caja de regalo', 'price' => 100, 'requires_text' => false]);
        $producto->addons()->attach([$grabado->id, $sinTexto->id]);

        $this->checkout([[
            'product_id' => $producto->id, 'quantity' => 1, 'variant_id' => $libre->id, 'custom_color_text' => 'Verde agua',
            'addon_selections' => [['addon_id' => $grabado->id, 'custom_text' => 'Para Ana'], ['addon_id' => $sinTexto->id, 'custom_text' => null]],
        ]])->assertOk();

        $item = $this->reservaUnica()['items'][0];
        $this->assertSame('Color: Verde agua · Grabado: Para Ana', $item['nota']);
        $this->assertEquals(900.0, $item['precio_unitario']); // 500 + 300 + 100
    }

    public function test_la_nota_se_recorta_a_500_caracteres_y_no_viaja_vacia(): void
    {
        $producto = $this->producto(['title' => 'Bengala', 'price' => 500]);
        $conTexto = $this->producto(['title' => 'Volcán', 'price' => 500]);
        $libre = $this->variante($conTexto, 'Otro color', 0, null, personalizada: true);

        // Sin color ni textos: la línea no lleva `nota`.
        $this->checkout([
            ['product_id' => $producto->id, 'quantity' => 1],
            ['product_id' => $conTexto->id, 'quantity' => 1, 'variant_id' => $libre->id, 'custom_color_text' => str_repeat('a', 450)],
        ])->assertOk();

        $items = $this->reservaUnica()['items'];
        $this->assertArrayNotHasKey('nota', $items[0]);
        $this->assertLessThanOrEqual(500, mb_strlen($items[1]['nota']));
        $this->assertStringStartsWith('Color: aaaa', $items[1]['nota']);
    }

    public function test_cupon_no_se_reparte_entre_las_lineas_total_es_la_suma_menos_el_descuento(): void
    {
        $a = $this->producto(['title' => 'A', 'price' => 1000]);
        $b = $this->producto(['title' => 'B', 'price' => 500]);
        $cupon = $this->codigo(['code' => 'DIEZ', 'percentage' => 10, 'usage_limit' => 5]);

        $respuesta = $this->checkout(
            [['product_id' => $a->id, 'quantity' => 2], ['product_id' => $b->id, 'quantity' => 1]],
            ['cart_discount_code' => 'DIEZ']
        )->assertOk();

        $payload = $this->reservaUnica();
        // Las líneas viajan a su precio final (sin el cupón) y el total ya trae el descuento: 2500 − 10 % = 2250.
        $this->assertEquals([1000.0, 500.0], array_column($payload['items'], 'precio_unitario'));
        $this->assertEquals(2250.0, $payload['total']);
        $this->assertStringContainsString('Cupón: DIEZ', $payload['cliente']['observaciones']);

        // El cupo se consumió una sola vez, ya con el pedido guardado.
        $pedido = Order::findOrFail($respuesta->json('order_id'));
        $this->assertSame($cupon->id, $pedido->discount_code_id);
        $this->assertNotNull($pedido->discount_usage_consumido_at);
        $this->assertSame(1, $cupon->fresh()->usage_count);
        $this->assertEquals(2250.0, (float) $pedido->total);
    }

    public function test_un_plan_de_tarjeta_viejo_en_la_sesion_ya_no_manda_total_con_recargo(): void
    {
        $producto = $this->producto(['price' => 1000]);
        $plan = CardPaymentPlan::factory()->create(['name' => '3 cuotas', 'installments' => 3, 'surcharge_percentage' => 20]);

        $this->checkout(
            [['product_id' => $producto->id, 'quantity' => 1]],
            ['cart_payment_plan' => ['id' => $plan->id, 'name' => '3 cuotas', 'installments' => 3, 'surcharge_percentage' => 20.0]]
        )->assertOk();

        $payload = $this->reservaUnica();
        $this->assertEquals(1000.0, $payload['total']);
        $this->assertArrayNotHasKey('total_con_recargo', $payload);
    }

    public function test_un_pedido_historico_con_recargo_de_tarjeta_sigue_mandando_total_con_recargo(): void
    {
        $pedido = new Order(['name' => 'Juana', 'phone' => '1122334455', 'total' => 1000, 'total_with_surcharge' => 1200]);

        $payload = app(PedidoParaCentral::class)->armar($pedido, [], now())->payload;

        $this->assertEquals(1000.0, $payload['total']);
        $this->assertEquals(1200.0, $payload['total_con_recargo']);
    }

    public function test_envio_gratis_se_avisa_en_las_observaciones_y_no_suma_al_total(): void
    {
        ['mixto' => $mixto, 'roja' => $roja] = $this->combosDeEjemplo();

        $this->checkout(
            [['combo_id' => $mixto->id, 'quantity' => 1, 'component_selections' => [['product_id' => $roja->product_id, 'variant_id' => $roja->id, 'custom_color_text' => null]]]],
            datos: ['observations' => 'Dejar con el vecino']
        )->assertOk();

        $payload = $this->reservaUnica();
        $this->assertSame("Envío gratis\nDejar con el vecino", $payload['cliente']['observaciones']);
        $this->assertEquals(8000.0, $payload['total']); // solo el combo, sin ninguna línea de envío
        $this->assertCount(1, $payload['items']);
    }

    public function test_envio_gratis_por_umbral_tambien_se_avisa(): void
    {
        Setting::set('free_shipping_threshold', '2000');
        $producto = $this->producto(['price' => 1000]);

        $this->checkout([['product_id' => $producto->id, 'quantity' => 2]])->assertOk();

        $this->assertSame('Envío gratis', $this->reservaUnica()['cliente']['observaciones']);
    }

    public function test_combo_fijo_viaja_como_combo_sin_selecciones(): void
    {
        ['fijo' => $fijo] = $this->combosDeEjemplo();

        $this->checkout([['combo_id' => $fijo->id, 'quantity' => 2, 'component_selections' => []]])->assertOk();

        $this->assertEquals([[
            'tipo' => 'combo', 'id' => (string) $fijo->id, 'nombre' => 'Combo fijo', 'cantidad' => 2, 'precio_unitario' => 1500.0,
        ]], $this->reservaUnica()['items']);
    }

    public function test_combo_con_eleccion_viaja_con_un_grupo_por_componente_y_el_nombre_del_catalogo(): void
    {
        ['mixto' => $mixto, 'pistola' => $pistola, 'a' => $a, 'azul' => $azul, 'lineaPistola' => $lineaPistola, 'lineaChispas' => $lineaChispas] = $this->combosDeEjemplo();

        $this->checkout([[
            'combo_id' => $mixto->id, 'quantity' => 2,
            'component_selections' => [['product_id' => $pistola->id, 'variant_id' => $azul->id, 'custom_color_text' => null]],
        ]])->assertOk();

        $item = $this->reservaUnica()['items'][0];
        $this->assertSame('combo', $item['tipo']);
        $this->assertSame((string) $mixto->id, $item['id']);
        $this->assertSame(2, $item['cantidad']);
        $this->assertEquals([
            ['grupo' => "Pistola (#{$lineaPistola->id})", 'opciones' => [['tipo' => 'variante', 'id' => (string) $azul->id, 'cantidad' => 1]]],
            ['grupo' => "Chispa A (#{$lineaChispas->id})", 'opciones' => [['tipo' => 'producto', 'id' => (string) $a->id, 'cantidad' => 2]]],
        ], $item['selecciones']);
        // Una variante concreta ya viaja como la opción elegida: no hay nada que aclarar en la nota.
        $this->assertArrayNotHasKey('nota', $item);
    }

    public function test_combo_con_color_a_eleccion_lleva_el_color_de_cada_componente_en_la_nota(): void
    {
        ['mixto' => $mixto, 'pistola' => $pistola, 'otro' => $otro] = $this->combosDeEjemplo();

        $this->checkout([[
            'combo_id' => $mixto->id, 'quantity' => 1,
            'component_selections' => [['product_id' => $pistola->id, 'variant_id' => $otro->id, 'custom_color_text' => 'Turquesa PRUEBA']],
        ]])->assertOk();

        $item = $this->reservaUnica()['items'][0];
        $this->assertSame('Pistola: Color: Turquesa PRUEBA', $item['nota']);
        $this->assertSame('variante', $item['selecciones'][0]['opciones'][0]['tipo']);
        $this->assertSame((string) $otro->id, $item['selecciones'][0]['opciones'][0]['id']);
    }

    public function test_los_datos_del_cliente_se_recortan_a_los_topes_del_central(): void
    {
        $producto = $this->producto();

        // La web admite hasta 20 caracteres de DNI y de CP: el Central acepta 30 y 10.
        $this->checkout([['product_id' => $producto->id, 'quantity' => 1]], datos: ['postal_code' => '12345678901234567890'])->assertOk();

        $this->assertSame(10, strlen($this->reservaUnica()['cliente']['cp']));
    }

    // ------------------------------------------------------------------------------------------------------------------
    // El Central rechaza o no responde
    // ------------------------------------------------------------------------------------------------------------------

    public function test_sin_stock_en_el_central_rechaza_con_producto_y_color_y_refresca_el_espejo(): void
    {
        $producto = $this->producto(['title' => 'Bengala', 'price' => 500]);
        $roja = $this->variante($producto, 'Roja', 0, 10); // el espejo cree que hay 10
        $this->central->disponible['variante:'.$roja->id] = 2;
        $this->central->stock['variante:'.$roja->id] = ['disponible' => 2];
        $cupon = $this->codigo(['code' => 'DIEZ', 'usage_limit' => 5]);

        $respuesta = $this->checkout([['product_id' => $producto->id, 'quantity' => 4, 'variant_id' => $roja->id]], ['cart_discount_code' => 'DIEZ']);

        // El mismo 422 de siempre (el espejo local aún decía 10): mensaje con producto y color, carrito intacto.
        $respuesta->assertStatus(422)->assertJson([
            'success' => false,
            'message' => 'Solo quedan 2 unidades de Bengala (Roja) (pediste 4). Ajustá tu carrito para continuar.',
            'stock_insuficiente' => [[
                'product_id' => $producto->id, 'product_title' => 'Bengala', 'product_variant_id' => $roja->id, 'variant_name' => 'Roja',
                'cantidad_solicitada' => 4, 'stock_disponible' => 2,
            ]],
        ]);
        $this->assertNotNull(session('cart'), 'El carrito no se pierde');

        // No se creó nada: ni pedido, ni reserva, ni cupón consumido.
        $this->assertSame(0, Order::count());
        $this->assertSame([], $this->central->vivos());
        $this->assertSame(0, $cupon->fresh()->usage_count);

        // El espejo se actualizó con lo que dijo el Central (2), así la tienda ya no ofrece 10.
        $this->assertSame(2, $roja->fresh()->stock);
        $this->assertSame(2, StockItem::where('tipo', 'variante')->where('external_id', (string) $roja->id)->value('disponible'));
    }

    public function test_el_rechazo_corrige_la_columna_aunque_el_cache_del_espejo_ya_estuviera_al_dia(): void
    {
        $producto = $this->producto(['title' => 'Bengala', 'price' => 500]);
        $roja = $this->variante($producto, 'Roja', 0, 9); // la columna quedó atrasada (9)…
        StockItem::query()->create(['tipo' => 'variante', 'external_id' => (string) $roja->id, 'disponible' => 1, 'vinculado' => true, 'version' => 5]); // …pero rukoz_stock ya dice 1
        $this->central->disponible['variante:'.$roja->id] = 1;
        $this->central->stock['variante:'.$roja->id] = ['disponible' => 1];

        $this->checkout([['product_id' => $producto->id, 'quantity' => 3, 'variant_id' => $roja->id]])->assertStatus(422);

        $this->assertSame(1, $roja->fresh()->stock);
    }

    public function test_sin_stock_total_dice_no_queda_stock_de_producto_y_color(): void
    {
        $producto = $this->producto(['title' => 'Pistola', 'price' => 100000]);
        $negra = $this->variante($producto, 'Negra', 0, 5);
        $this->central->disponible['variante:'.$negra->id] = 0;

        $this->checkout([['product_id' => $producto->id, 'quantity' => 2, 'variant_id' => $negra->id]])
            ->assertStatus(422)
            ->assertJsonPath('message', 'No queda stock de Pistola (Negra). Ajustá tu carrito para continuar.');

        $this->assertSame(0, Order::count());
    }

    public function test_el_rechazo_por_un_combo_nombra_el_combo(): void
    {
        ['fijo' => $fijo] = $this->combosDeEjemplo();
        $this->central->disponible['combo:'.$fijo->id] = 0;

        $this->checkout([['combo_id' => $fijo->id, 'quantity' => 1, 'component_selections' => []]])
            ->assertStatus(422)
            ->assertJsonPath('message', 'No queda stock de combo "Combo fijo". Ajustá tu carrito para continuar.');
    }

    public function test_central_caido_no_crea_el_pedido_y_ofrece_whatsapp_con_el_carrito_armado(): void
    {
        $producto = $this->producto(['title' => 'Chispa simple', 'price' => 1000, 'stock' => 10]);
        $this->central->caido = true;

        $respuesta = $this->checkout([['product_id' => $producto->id, 'quantity' => 2]]);

        $respuesta->assertStatus(503)->assertJson([
            'success' => false,
            'message' => 'No pudimos confirmar el stock en este momento. Probá de nuevo en unos minutos o escribinos por WhatsApp.',
            'central_no_disponible' => true,
        ]);
        // El carrito armado en el texto de WhatsApp, y el carrito de la sesión no se pierde.
        $this->assertStringContainsString('NUEVO PEDIDO DE LA WEB', $respuesta->json('whatsapp_message'));
        $this->assertStringContainsString('Chispa simple', $respuesta->json('whatsapp_message'));
        $this->assertNotNull(session('cart'));

        $this->assertSame(0, Order::count());
        $this->assertSame(10, $producto->fresh()->stock); // y no vendió con stock local
    }

    public function test_central_caido_el_reintento_reusa_la_referencia_y_no_duplica_la_reserva(): void
    {
        $producto = $this->producto(['price' => 1000]);
        $carrito = [['product_id' => $producto->id, 'quantity' => 1]];

        $this->central->caido = true;
        $this->checkout($carrito)->assertStatus(503);
        $refDelPrimerIntento = $this->central->llamadas[0][2]['external_ref'];

        // Quedó un «crear dudoso»: el Central pudo haber reservado antes de cortarse.
        $this->assertSame(1, Compensacion::query()->pendientes()->where('tipo', Compensacion::CREAR_DUDOSO)->count());

        $this->central->caido = false;
        $respuesta = $this->checkout($carrito);

        $respuesta->assertOk();
        $this->assertSame($refDelPrimerIntento, Order::findOrFail($respuesta->json('order_id'))->rukoz_ref);
        $this->assertCount(1, $this->central->vivos());
        // Al adoptar el pedido, la compensación dudosa ya no tiene sentido (cancelaría una reserva buena).
        $this->assertSame(0, Compensacion::query()->pendientes()->count());
    }

    public function test_circuito_abierto_no_llama_al_central_y_no_vende(): void
    {
        $producto = $this->producto(['price' => 1000]);

        for ($i = 0; $i < 5; $i++) {
            app(Circuito::class)->falla();
        }

        $this->assertTrue(app(Circuito::class)->abierto());

        $this->checkout([['product_id' => $producto->id, 'quantity' => 1]])
            ->assertStatus(503)
            ->assertJson(['central_no_disponible' => true]);

        $this->assertSame([], $this->central->llamadas);
        $this->assertSame(0, Order::count());
    }

    public function test_si_se_permite_vender_sin_reserva_el_central_caido_cae_al_flujo_local(): void
    {
        config(['integracion.checkout_sin_reserva' => true]);
        $producto = $this->producto(['price' => 1000, 'stock' => 10]);
        $this->central->caido = true;

        $respuesta = $this->checkout([['product_id' => $producto->id, 'quantity' => 2]]);

        $respuesta->assertOk();
        $pedido = Order::findOrFail($respuesta->json('order_id'));
        $this->assertNull($pedido->rukoz_ref);
        $this->assertSame(8, $producto->fresh()->stock); // stock local de siempre
    }

    // ------------------------------------------------------------------------------------------------------------------
    // Doble envío
    // ------------------------------------------------------------------------------------------------------------------

    public function test_doble_envio_identico_crea_un_solo_pedido_y_una_sola_reserva(): void
    {
        $producto = $this->producto(['price' => 1000]);
        $carrito = [['product_id' => $producto->id, 'quantity' => 2]];

        $primero = $this->checkout($carrito)->assertOk();
        $segundo = $this->checkout($carrito)->assertOk();

        $this->assertSame($primero->json('order_id'), $segundo->json('order_id'));
        $this->assertSame(1, Order::count());
        $this->assertCount(1, $this->central->vivos());
        $this->assertCount(1, $this->central->llamadasA('POST', '#^/pedidos$#'), 'El segundo envío no tiene que volver a reservar');
    }

    public function test_un_carrito_distinto_es_otro_pedido_con_otra_referencia(): void
    {
        $producto = $this->producto(['price' => 1000]);

        $this->checkout([['product_id' => $producto->id, 'quantity' => 1]])->assertOk();
        $this->checkout([['product_id' => $producto->id, 'quantity' => 2]])->assertOk();

        $this->assertSame(2, Order::count());
        $this->assertSame(2, Order::distinct()->count('rukoz_ref'));
    }

    public function test_repetir_la_compra_pasada_la_ventana_de_doble_envio_es_un_pedido_nuevo(): void
    {
        $producto = $this->producto(['price' => 1000]);
        $carrito = [['product_id' => $producto->id, 'quantity' => 1]];

        $this->checkout($carrito)->assertOk();
        $this->travel(5)->minutes();
        $this->checkout($carrito)->assertOk();

        $this->assertSame(2, Order::count());
    }

    // ------------------------------------------------------------------------------------------------------------------
    // La transacción local falla después de reservar
    // ------------------------------------------------------------------------------------------------------------------

    public function test_si_falla_la_transaccion_local_se_cancela_la_reserva(): void
    {
        $producto = $this->producto(['price' => 1000]);
        Order::creating(fn () => throw new RuntimeException('falló la base'));

        $respuesta = $this->checkout([['product_id' => $producto->id, 'quantity' => 1]]);

        $respuesta->assertStatus(500)->assertJson(['success' => false, 'message' => 'No pudimos registrar tu pedido. Por favor, intentá nuevamente.']);
        $this->assertSame(0, Order::count());
        $this->assertSame([], $this->central->vivos(), 'La reserva se tiene que haber cancelado');
        $this->assertSame('cancelado', array_values($this->central->pedidos)[0]['estado']);
        $this->assertSame(0, Compensacion::query()->pendientes()->count());
        $this->assertNotNull(session('cart'), 'El carrito no se pierde');
    }

    public function test_si_falla_local_y_tambien_la_cancelacion_queda_en_las_compensaciones_y_se_reintenta(): void
    {
        $producto = $this->producto(['price' => 1000]);
        // Falla la base local Y el Central se cae justo antes de que el paquete intente cancelar.
        Order::creating(function () {
            $this->central->caido = true;

            throw new RuntimeException('falló la base');
        });

        $this->checkout([['product_id' => $producto->id, 'quantity' => 1]])->assertStatus(500);

        $this->assertSame(0, Order::count());
        $this->assertCount(1, $this->central->vivos(), 'El Central todavía la tiene reservada');
        $this->assertSame(1, Compensacion::query()->pendientes()->where('tipo', Compensacion::CANCELAR)->count());

        // Vuelve el Central y corre el cron de compensaciones del paquete.
        $this->central->caido = false;
        $this->travel(2)->minutes();
        $this->artisan('rukoz:compensaciones')->assertSuccessful();

        $this->assertSame([], $this->central->vivos());
        $this->assertSame(0, Compensacion::query()->pendientes()->count());
    }

    public function test_si_el_cupon_se_agota_entre_el_calculo_y_el_guardado_se_cancela_la_reserva(): void
    {
        $producto = $this->producto(['price' => 1000]);
        $cupon = $this->codigo(['code' => 'UNICO', 'usage_limit' => 1]);

        // Otro cliente se lleva el último uso mientras este pedido reservaba.
        $this->central->alCrear = fn () => $cupon->update(['usage_count' => 1]);

        $respuesta = $this->checkout([['product_id' => $producto->id, 'quantity' => 1]], ['cart_discount_code' => 'UNICO']);

        $respuesta->assertStatus(422);
        $this->assertStringContainsString('UNICO', $respuesta->json('message'));
        $this->assertSame(0, Order::count());
        $this->assertSame([], $this->central->vivos());
        $this->assertSame(1, $cupon->fresh()->usage_count);
    }

    // ------------------------------------------------------------------------------------------------------------------
    // Regla de encendido
    // ------------------------------------------------------------------------------------------------------------------

    public function test_checkout_sin_el_espejo_de_stock_no_usa_el_central_y_deja_un_warning(): void
    {
        config(['integracion.stock' => false]);
        $producto = $this->producto(['price' => 1000, 'stock' => 10]);

        $mensajes = [];
        Event::listen(MessageLogged::class, function (MessageLogged $e) use (&$mensajes) {
            $mensajes[] = [$e->level, $e->message];
        });

        $this->assertFalse(CheckoutCentral::activo());
        CheckoutCentral::advertir();
        $respuesta = $this->checkout([['product_id' => $producto->id, 'quantity' => 2]]);

        $respuesta->assertOk();
        $pedido = Order::findOrFail($respuesta->json('order_id'));
        $this->assertNull($pedido->rukoz_ref);
        $this->assertSame(8, $producto->fresh()->stock);
        $this->assertSame([], $this->central->llamadas);
        $this->assertContains('warning', array_column($mensajes, 0));
        $this->assertStringContainsString('falta INTEGRACION_STOCK', collect($mensajes)->firstWhere(0, 'warning')[1]);
    }

    public function test_checkout_sin_la_integracion_general_no_usa_el_central(): void
    {
        config(['rukoz.habilitada' => false]);
        $producto = $this->producto(['price' => 1000, 'stock' => 10]);

        $this->assertFalse(CheckoutCentral::activo());
        $this->assertStringContainsString('falta RUKOZ_INTEGRACION', (string) CheckoutCentral::advertencia());

        $this->checkout([['product_id' => $producto->id, 'quantity' => 2]])->assertOk();

        $this->assertSame([], $this->central->llamadas);
        $this->assertSame(8, $producto->fresh()->stock);
        $this->assertNull(Order::first()->rukoz_ref);
    }

    public function test_con_el_interruptor_apagado_el_checkout_no_toca_el_central(): void
    {
        config(['integracion.checkout' => false]);
        $producto = $this->producto(['price' => 1000, 'stock' => 10]);

        $this->assertNull(CheckoutCentral::advertencia());
        $this->checkout([['product_id' => $producto->id, 'quantity' => 2]])->assertOk();

        $this->assertSame([], $this->central->llamadas);
        $this->assertSame(8, $producto->fresh()->stock);
        $this->assertNull(Order::first()->rukoz_ref);
    }

    // ------------------------------------------------------------------------------------------------------------------
    // Privacidad y errores del catálogo
    // ------------------------------------------------------------------------------------------------------------------

    public function test_la_nota_y_los_datos_del_cliente_no_se_loguean_aunque_el_central_rechace(): void
    {
        $producto = $this->producto(['title' => 'Bengala', 'price' => 500]);
        $libre = $this->variante($producto, 'Otro color', 0, null, personalizada: true);
        $this->central->disponible['variante:'.$libre->id] = 0;

        $logueado = [];
        Event::listen(MessageLogged::class, function (MessageLogged $e) use (&$logueado) {
            $logueado[] = $e->message.' '.json_encode($e->context);
        });

        $this->checkout(
            [['product_id' => $producto->id, 'quantity' => 1, 'variant_id' => $libre->id, 'custom_color_text' => 'Turquesa secreto']],
            datos: ['name' => 'Persona Reservada']
        )->assertStatus(422);

        Log::info('marca para comprobar que el listener escucha');
        $this->assertNotEmpty($logueado);

        foreach ($logueado as $linea) {
            $this->assertStringNotContainsString('Turquesa secreto', $linea);
            $this->assertStringNotContainsString('Persona Reservada', $linea);
        }
    }

    public function test_item_no_vinculado_no_crea_pedido_y_muestra_un_mensaje_general(): void
    {
        $producto = $this->producto(['price' => 1000]);
        // Un Central que contesta ITEM_NO_VINCULADO (el fixture oficial del contrato) para todo pedido.
        $this->central->errorForzado = [422, json_decode(file_get_contents(base_path('tests/fixtures/rukoz/error-item-no-vinculado.json')), true)];

        $respuesta = $this->checkout([['product_id' => $producto->id, 'quantity' => 1]]);

        $respuesta->assertStatus(422)->assertJson([
            'success' => false,
            'message' => 'Uno de los productos no está disponible por el momento. Escribinos por WhatsApp y lo resolvemos.',
        ]);
        $this->assertSame(0, Order::count());
        $this->assertNotNull(session('cart'));
    }
}
