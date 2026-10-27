<?php

namespace Tests\Feature;

use App\Enums\TipoEntrega;
use App\Models\CardPaymentPlan;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Support\Provincias;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CentralDePrueba;
use Tests\TestCase;

/**
 * Checkout mínimo: «Envío / Retiro en oficina». Envío pide nombre y apellido (un solo campo), WhatsApp y provincia; retiro, nombre y
 * WhatsApp (la oficina es una sola: Buenos Aires). DNI, localidad, código postal, email y apellido aparte ya no los pide la tienda pero
 * el servidor los sigue aceptando (opcionales). El mensaje de WhatsApp se arma con la orden ya creada ("Pedido #N").
 */
class CheckoutMinimoTest extends TestCase
{
    use RefreshDatabase;

    private Product $producto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producto = Product::factory()->for(Category::factory())->create(['title' => 'Chispa 2m', 'price' => 1000, 'stock' => 50, 'is_active' => true]);
    }

    private function datos(array $extra = []): array
    {
        return ['name' => 'Ana Pérez', 'phone' => '11 5555-1234', ...$extra];
    }

    private function comprar(array $datos): \Illuminate\Testing\TestResponse
    {
        return $this->withSession(['cart' => [['product_id' => $this->producto->id, 'quantity' => 2]]])
            ->postJson(route('cart.whatsapp'), ['customer_data' => $datos]);
    }

    // ------------------------------------------------------------------------------------------------------------------
    // Las 24 provincias
    // ------------------------------------------------------------------------------------------------------------------

    public function test_hay_24_provincias_y_los_slugs_que_ya_estaban_guardados_no_cambian(): void
    {
        $this->assertCount(24, Provincias::all());
        $this->assertSame(array_keys(Provincias::all()), Provincias::slugs());

        foreach (['buenos-aires' => 'Buenos Aires', 'caba' => 'Ciudad Autónoma de Buenos Aires', 'cordoba' => 'Córdoba', 'santa-fe' => 'Santa Fe',
            'mendoza' => 'Mendoza', 'tucuman' => 'Tucumán', 'salta' => 'Salta', 'entre-rios' => 'Entre Ríos'] as $slug => $nombre) {
            $this->assertSame($nombre, Provincias::nombre($slug), "El slug {$slug} guardado en pedidos viejos tiene que seguir andando");
        }

        foreach (['Catamarca', 'Chaco', 'Chubut', 'Corrientes', 'Formosa', 'Jujuy', 'La Pampa', 'La Rioja', 'Misiones', 'Neuquén', 'Río Negro',
            'San Juan', 'San Luis', 'Santa Cruz', 'Santiago del Estero', 'Tierra del Fuego'] as $nombre) {
            $this->assertContains($nombre, array_column(Provincias::all(), 'name'));
        }

        // Un slug raro de un dato viejo/manual no rompe: se arma un título.
        $this->assertSame('Otra Cosa', Provincias::nombre('otra-cosa'));
        $this->assertNull(Provincias::nombre(null));
    }

    public function test_el_checkout_ofrece_las_24_provincias_y_la_oficina_de_retiro(): void
    {
        $this->withSession(['cart' => [['product_id' => $this->producto->id, 'quantity' => 1]]])
            ->get(route('cart.checkout'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cart/Checkout')
                ->has('provinces', 24)
                ->where('provinces.neuquen.name', 'Neuquén')
                ->where('oficina', 'Retiro en oficina · Buenos Aires')
            );
    }

    public function test_cada_una_de_las_24_provincias_se_acepta_en_un_envio(): void
    {
        foreach (Provincias::slugs() as $slug) {
            // El límite de pedidos por minuto es otro tema (ThrottlePublicoTest): acá se prueban las 24 de corrido.
            Cache::flush();

            $respuesta = $this->comprar($this->datos(['tipo_entrega' => 'envio', 'province' => $slug]))->assertOk();

            $this->assertSame($slug, Order::findOrFail($respuesta->json('order_id'))->province);
        }
    }

    public function test_una_provincia_que_no_esta_en_la_lista_se_rechaza(): void
    {
        $this->comprar($this->datos(['tipo_entrega' => 'envio', 'province' => 'atlantida']))
            ->assertStatus(422)->assertJsonValidationErrors(['customer_data.province']);

        $this->assertSame(0, Order::count());
    }

    // ------------------------------------------------------------------------------------------------------------------
    // Envío
    // ------------------------------------------------------------------------------------------------------------------

    public function test_un_envio_se_guarda_con_su_tipo_el_nombre_entero_y_lo_demas_vacio(): void
    {
        $respuesta = $this->comprar($this->datos(['tipo_entrega' => 'envio', 'province' => 'mendoza']))->assertOk();

        $pedido = Order::findOrFail($respuesta->json('order_id'));
        $this->assertSame(TipoEntrega::Envio, $pedido->tipo_entrega);
        $this->assertFalse($pedido->esRetiro());
        $this->assertSame('Ana Pérez', $pedido->name, 'El nombre y apellido va entero en name');
        $this->assertNull($pedido->lastname);
        $this->assertSame('Ana Pérez', $pedido->nombreCompleto());
        $this->assertSame('11 5555-1234', $pedido->phone);
        $this->assertSame('mendoza', $pedido->province);
        $this->assertNull($pedido->dni);
        $this->assertNull($pedido->city);
        $this->assertNull($pedido->postal_code);
        $this->assertNull($pedido->email);
    }

    public function test_sin_tipo_de_entrega_es_un_envio_como_antes(): void
    {
        $respuesta = $this->comprar($this->datos(['province' => 'cordoba']))->assertOk();

        $this->assertSame(TipoEntrega::Envio, Order::findOrFail($respuesta->json('order_id'))->tipo_entrega);
    }

    public function test_los_campos_de_antes_siguen_aceptandose_como_opcionales(): void
    {
        $respuesta = $this->comprar($this->datos([
            'tipo_entrega' => 'envio', 'province' => 'salta', 'lastname' => 'Gómez', 'dni' => '30111222',
            'city' => 'Salta Capital', 'postal_code' => '4400', 'email' => 'ana@example.com',
        ]))->assertOk();

        $pedido = Order::findOrFail($respuesta->json('order_id'));
        $this->assertSame('Gómez', $pedido->lastname);
        $this->assertSame('Ana Pérez Gómez', $pedido->nombreCompleto());
        $this->assertSame('30111222', $pedido->dni);
        $this->assertSame('Salta Capital', $pedido->city);
        $this->assertSame('4400', $pedido->postal_code);
        $this->assertSame('ana@example.com', $pedido->email);

        $this->assertStringContainsString("DNI: 30111222\n", $pedido->mensaje_whatsapp);
        $this->assertStringContainsString("Localidad: Salta Capital\n", $pedido->mensaje_whatsapp);
        $this->assertStringContainsString("Código Postal: 4400\n", $pedido->mensaje_whatsapp);
        $this->assertStringContainsString("Email: ana@example.com\n", $pedido->mensaje_whatsapp);
    }

    public function test_un_envio_exige_provincia(): void
    {
        $this->comprar($this->datos(['tipo_entrega' => 'envio']))
            ->assertStatus(422)->assertJsonValidationErrors(['customer_data.province']);

        $this->assertSame(0, Order::count());
        $this->assertSame(50, $this->producto->fresh()->stock);
    }

    // ------------------------------------------------------------------------------------------------------------------
    // Retiro en oficina
    // ------------------------------------------------------------------------------------------------------------------

    public function test_un_retiro_se_guarda_sin_provincia(): void
    {
        $respuesta = $this->comprar($this->datos(['tipo_entrega' => 'retiro']))->assertOk();

        $pedido = Order::findOrFail($respuesta->json('order_id'));
        $this->assertSame(TipoEntrega::Retiro, $pedido->tipo_entrega);
        $this->assertTrue($pedido->esRetiro());
        $this->assertNull($pedido->province);
    }

    public function test_un_retiro_no_exige_provincia_y_si_llega_se_descarta(): void
    {
        $respuesta = $this->comprar($this->datos(['tipo_entrega' => 'retiro', 'province' => 'mendoza']))->assertOk();

        $this->assertNull(Order::findOrFail($respuesta->json('order_id'))->province);
    }

    // ------------------------------------------------------------------------------------------------------------------
    // Validaciones comunes
    // ------------------------------------------------------------------------------------------------------------------

    public function test_el_nombre_y_el_telefono_son_obligatorios_en_los_dos_tipos(): void
    {
        foreach ([['tipo_entrega' => 'envio', 'province' => 'salta'], ['tipo_entrega' => 'retiro']] as $tipo) {
            $this->comprar(['phone' => '11 5555-1234', ...$tipo])->assertStatus(422)->assertJsonValidationErrors(['customer_data.name']);
            $this->comprar(['name' => 'Ana Pérez', ...$tipo])->assertStatus(422)->assertJsonValidationErrors(['customer_data.phone']);
        }

        $this->assertSame(0, Order::count());
    }

    public function test_el_telefono_necesita_al_menos_8_digitos(): void
    {
        $this->comprar($this->datos(['tipo_entrega' => 'retiro', 'phone' => '555-123']))
            ->assertStatus(422)->assertJsonValidationErrors(['customer_data.phone']);

        $this->comprar($this->datos(['tipo_entrega' => 'retiro', 'phone' => '+54 9 11 5555-1234']))->assertOk();
    }

    public function test_un_tipo_de_entrega_desconocido_se_rechaza(): void
    {
        $this->comprar($this->datos(['tipo_entrega' => 'domicilio', 'province' => 'salta']))
            ->assertStatus(422)->assertJsonValidationErrors(['customer_data.tipo_entrega']);
    }

    // ------------------------------------------------------------------------------------------------------------------
    // El mensaje de WhatsApp
    // ------------------------------------------------------------------------------------------------------------------

    public function test_el_mensaje_de_un_envio_lleva_el_numero_de_pedido_la_provincia_y_la_forma_de_pago_a_coordinar(): void
    {
        $respuesta = $this->comprar($this->datos(['tipo_entrega' => 'envio', 'province' => 'neuquen']))->assertOk();

        $pedido = Order::findOrFail($respuesta->json('order_id'));
        $mensaje = $respuesta->json('message');

        $this->assertSame($mensaje, $pedido->mensaje_whatsapp, 'Se guarda el texto final, el mismo que sale por WhatsApp');
        $this->assertStringStartsWith("🛒 *NUEVO PEDIDO DE LA WEB*\n*Pedido #{$pedido->id}*\n📍 Envío (Neuquén)\n", $mensaje);
        $this->assertStringContainsString("Nombre: Ana Pérez\nTeléfono: 11 5555-1234\n", $mensaje);
        $this->assertStringContainsString('*TOTAL: $2.000*', $mensaje);
        $this->assertStringEndsWith('💳 *Forma de pago:* a coordinar por WhatsApp', $mensaje);
    }

    public function test_el_mensaje_de_un_retiro_dice_retiro_en_oficina_y_no_habla_de_envio(): void
    {
        \App\Models\Setting::set('free_shipping_threshold', '1000');

        $respuesta = $this->comprar($this->datos(['tipo_entrega' => 'retiro']))->assertOk();

        $mensaje = $respuesta->json('message');
        $this->assertStringContainsString("📍 Retiro en oficina · Buenos Aires\n", $mensaje);
        $this->assertStringNotContainsString('Envío (', $mensaje);
        $this->assertStringNotContainsString('Envío gratis', $mensaje);
        $this->assertStringNotContainsString('envío gratis', $mensaje);
        $this->assertStringContainsString('*Pedido #'.$respuesta->json('order_id').'*', $mensaje);
    }

    public function test_el_mensaje_no_tiene_lineas_de_datos_vacias(): void
    {
        $mensaje = $this->comprar($this->datos(['tipo_entrega' => 'envio', 'province' => 'salta']))->assertOk()->json('message');

        foreach (['DNI', 'Email', 'Localidad', 'Código Postal', 'Observaciones'] as $etiqueta) {
            $this->assertStringNotContainsString($etiqueta, $mensaje, "«{$etiqueta}» no debe aparecer si no hay dato");
        }

        $this->assertDoesNotMatchRegularExpression('/: *\n/', $mensaje, 'Ninguna línea "Etiqueta:" sin valor');
        $this->assertDoesNotMatchRegularExpression('/\n\n\n/', $mensaje, 'Sin renglones vacíos de más');
    }

    public function test_la_nota_del_cliente_va_en_el_mensaje(): void
    {
        $mensaje = $this->comprar($this->datos(['tipo_entrega' => 'envio', 'province' => 'salta', 'observations' => 'Color a elección: violeta']))
            ->assertOk()->json('message');

        $this->assertStringContainsString("📝 *Observaciones:*\nColor a elección: violeta\n", $mensaje);
    }

    public function test_el_cliente_no_elige_la_forma_de_pago_y_el_pedido_no_guarda_plan(): void
    {
        $plan = CardPaymentPlan::factory()->create();

        $respuesta = $this->comprar($this->datos(['tipo_entrega' => 'retiro', 'plan_pago_tarjeta_id' => $plan->id]))->assertOk();

        $pedido = Order::findOrFail($respuesta->json('order_id'));
        $this->assertNull($pedido->card_payment_plan_id);
        $this->assertNull($pedido->total_with_surcharge);
    }

    // ------------------------------------------------------------------------------------------------------------------
    // Con el Sistema Central
    // ------------------------------------------------------------------------------------------------------------------

    public function test_con_el_central_el_pedido_reserva_con_los_datos_minimos_y_el_mensaje_lleva_su_numero(): void
    {
        $central = CentralDePrueba::instalar();

        $respuesta = $this->comprar($this->datos(['tipo_entrega' => 'retiro']))->assertOk();

        $this->assertSame([], $central->erroresDeContrato, 'El Central no debería rechazar un pedido sin DNI, localidad, CP ni email');

        $pedido = Order::findOrFail($respuesta->json('order_id'));
        $this->assertNotNull($pedido->rukoz_ref);
        $this->assertStringContainsString("*Pedido #{$pedido->id}*", $pedido->mensaje_whatsapp);
        $this->assertSame($respuesta->json('message'), $pedido->mensaje_whatsapp);

        $cliente = $central->pedidos[$pedido->rukoz_ref]['payload']['cliente'];
        $this->assertSame(['nombre' => 'Ana Pérez', 'telefono' => '11 5555-1234'], $cliente);
    }

    public function test_con_el_central_caido_no_se_crea_el_pedido_y_se_conserva_el_mensaje_previo_sin_numero(): void
    {
        $central = CentralDePrueba::instalar();
        $central->caido = true;

        $respuesta = $this->comprar($this->datos(['tipo_entrega' => 'envio', 'province' => 'chubut']));

        $respuesta->assertStatus(503)->assertJson(['central_no_disponible' => true]);

        $mensaje = $respuesta->json('whatsapp_message');
        $this->assertStringContainsString('NUEVO PEDIDO DE LA WEB', $mensaje);
        $this->assertStringContainsString('📍 Envío (Chubut)', $mensaje);
        $this->assertStringContainsString('Nombre: Ana Pérez', $mensaje);
        $this->assertStringNotContainsString('Pedido #', $mensaje, 'Todavía no hay número de pedido');
        $this->assertStringEndsWith('a coordinar por WhatsApp', $mensaje);
        $this->assertSame(0, Order::count());
        $this->assertNotNull(session('cart'), 'El carrito no se pierde');
    }
}
