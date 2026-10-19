<?php

namespace Tests\Feature;

use App\Enums\AlcanceOferta;
use App\Enums\TipoDescuento;
use App\Models\Addon;
use App\Models\CardPaymentPlan;
use App\Models\Category;
use App\Models\Combo;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\ProductPriceTier;
use App\Models\ProductVariant;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests de CARACTERIZACIÓN del checkout (POST /carrito/whatsapp): fijan, con valores
 * literales calculados a mano, qué escribe hoy el checkout para varios carritos reales
 * (producto simple, escala + oferta + color con recargo + add-on, oferta fija, color "a
 * elección", combo fijo, combo con elección de color, cupón, plan de tarjeta, envío gratis
 * y los rechazos).
 *
 * Existen para poder extraer el cálculo del pedido (CalculadoraPedido) sin cambiar el
 * comportamiento: se escribieron ANTES del refactor, contra el CartController original, y
 * tienen que seguir dando exactamente lo mismo después. Si alguno falla tras tocar el
 * checkout, el que se movió es el comportamiento, no el test.
 */
class CheckoutCaracterizacionTest extends TestCase
{
    use RefreshDatabase;

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

    private function checkout(array $carrito, array $sesionExtra = [], array $datos = []): \Illuminate\Testing\TestResponse
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

    private function codigo(array $attrs = []): DiscountCode
    {
        return DiscountCode::create(array_merge([
            'code' => 'VERANO10', 'percentage' => 10, 'min_purchase_amount' => null,
            'usage_limit' => null, 'usage_count' => 0, 'start_date' => null, 'end_date' => null,
            'is_active' => true,
        ], $attrs));
    }

    /** Campos económicos del pedido, en tipos estables (floats/ints/strings). */
    private function resumenOrden(Order $o): array
    {
        return [
            'estado' => $o->estado->value,
            'subtotal' => (float) $o->subtotal,
            'discount_amount' => (float) $o->discount_amount,
            'total' => (float) $o->total,
            'free_shipping' => (bool) $o->free_shipping,
            'discount_code' => $o->discount_code,
            'plan' => $o->payment_plan_name,
            'cuotas' => $o->payment_plan_installments,
            'recargo_pct' => $o->surcharge_percentage === null ? null : (float) $o->surcharge_percentage,
            'recargo' => $o->surcharge_amount === null ? null : (float) $o->surcharge_amount,
            'total_con_recargo' => $o->total_with_surcharge === null ? null : (float) $o->total_with_surcharge,
            'items' => $o->items->sortBy('id')->map(fn ($i) => [
                'product_id' => $i->product_id,
                'combo_id' => $i->combo_id,
                'variant_id' => $i->product_variant_id,
                'titulo' => $i->product_title,
                'variante' => $i->variant_name,
                'recargo_variante' => $i->variant_price_addon === null ? null : (float) $i->variant_price_addon,
                'color_libre' => $i->custom_color_text,
                'addons' => $i->addons_selected,
                'addons_total' => $i->addons_total === null ? null : (float) $i->addons_total,
                'cantidad' => $i->cantidad,
                'precio_unitario' => (float) $i->precio_unitario,
                'base' => (float) $i->base_unit_price,
                'subtotal' => (float) $i->subtotal,
            ])->values()->all(),
        ];
    }

    // --- producto simple ----------------------------------------------------------

    public function test_producto_simple_sin_opciones(): void
    {
        $p = $this->producto(['title' => 'Chispa 2m', 'price' => 1000, 'stock' => 50]);

        $r = $this->checkout([['product_id' => $p->id, 'quantity' => 3]]);

        $r->assertOk()->assertJson([
            'success' => true,
            'subtotal' => 3000.0,
            'discount_amount' => 0.0,
            'total' => 3000.0,
            'payment_plan' => null,
            'itemCount' => 1,
        ]);

        $o = Order::with('items')->sole();
        $this->assertSame($o->id, $r->json('order_id'));
        $this->assertSame([
            'estado' => 'pendiente',
            'subtotal' => 3000.0, 'discount_amount' => 0.0, 'total' => 3000.0,
            'free_shipping' => false, 'discount_code' => null,
            'plan' => null, 'cuotas' => null, 'recargo_pct' => null, 'recargo' => null, 'total_con_recargo' => null,
            'items' => [[
                'product_id' => $p->id, 'combo_id' => null, 'variant_id' => null,
                'titulo' => 'Chispa 2m', 'variante' => null, 'recargo_variante' => 0.0,
                'color_libre' => null, 'addons' => null, 'addons_total' => 0.0,
                'cantidad' => 3, 'precio_unitario' => 1000.0, 'base' => 1000.0, 'subtotal' => 3000.0,
            ]],
        ], $this->resumenOrden($o));

        $this->assertSame(47, $p->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $p->id, 'order_id' => $o->id, 'cantidad' => -3, 'stock_resultante' => 47]);
        $this->assertNull(session('cart'));

        $this->assertSame(
            "🛒 *NUEVO PEDIDO DE LA WEB*\n\n"
            ."👤 *Datos del Cliente:*\n"
            ."Nombre: Juana Pérez\n"
            ."DNI: 30111222\n"
            ."Teléfono: 1122334455\n"
            ."Email: juana@example.com\n\n"
            ."📍 *Envío a Sucursal:*\n"
            ."Provincia: buenos-aires\n"
            ."Ciudad: La Plata\n"
            ."Código Postal: 1900\n\n"
            ."📋 *Detalle del pedido:*\n"
            ."• Chispa 2m\n"
            ."  Cantidad: 3\n"
            ."  Precio: \$1.000\n"
            ."  Subtotal: \$3.000\n\n"
            .'💰 *TOTAL: $3.000*',
            $o->mensaje_whatsapp
        );
        $this->assertSame($o->mensaje_whatsapp, $r->json('message'));
    }

    public function test_varias_lineas_de_productos_distintos_suman_subtotal_y_descuentan_cada_stock(): void
    {
        $a = $this->producto(['title' => 'A', 'price' => 1250, 'stock' => 10]);
        $b = $this->producto(['title' => 'B', 'price' => 399.5, 'stock' => 5]);

        $this->checkout([
            ['product_id' => $a->id, 'quantity' => 2],
            ['product_id' => $b->id, 'quantity' => 3],
        ])->assertOk()->assertJson(['subtotal' => 3698.5, 'total' => 3698.5, 'itemCount' => 2]);

        $o = Order::with('items')->sole();
        $this->assertEquals(3698.5, (float) $o->total);
        $this->assertSame(
            [[2, 1250.0, 2500.0], [3, 399.5, 1198.5]],
            $o->items->sortBy('id')->map(fn ($i) => [$i->cantidad, (float) $i->precio_unitario, (float) $i->subtotal])->values()->all()
        );
        $this->assertSame(8, $a->fresh()->stock);
        $this->assertSame(2, $b->fresh()->stock);
        $this->assertStringContainsString('💰 *TOTAL: $3.699*', $o->mensaje_whatsapp);
    }

    // --- escala + oferta + color con recargo + add-on ----------------------------------

    public function test_escala_oferta_porcentual_color_con_recargo_y_addon_con_texto(): void
    {
        $p = $this->producto(['title' => 'Chispa 2m', 'price' => 1000, 'stock' => 99]);
        ProductPriceTier::factory()->create(['product_id' => $p->id, 'cantidad_minima' => 10, 'precio_unitario' => 800]);
        ProductOffer::create([
            'product_id' => $p->id, 'tipo_descuento' => TipoDescuento::Porcentaje, 'valor_descuento' => 10,
            'alcance' => AlcanceOferta::Todos, 'is_active' => true,
        ]);
        $rojo = ProductVariant::factory()->create(['product_id' => $p->id, 'name' => 'Rojo', 'price_addon' => 150, 'stock' => 20]);
        $grabado = Addon::factory()->conTexto()->create(['name' => 'Grabado', 'price' => 300]);
        $p->addons()->attach($grabado->id);

        $r = $this->checkout([[
            'product_id' => $p->id, 'quantity' => 10, 'variant_id' => $rojo->id,
            'addon_selections' => [['addon_id' => $grabado->id, 'custom_text' => 'Feliz 2026']],
        ]]);

        // escala 800 → oferta -10% = 720 → +150 color +300 add-on = 1170 × 10
        $r->assertOk()->assertJson(['subtotal' => 11700.0, 'total' => 11700.0]);

        $o = Order::with('items')->sole();
        $this->assertSame([[
            'product_id' => $p->id, 'combo_id' => null, 'variant_id' => $rojo->id,
            'titulo' => 'Chispa 2m', 'variante' => 'Rojo', 'recargo_variante' => 150.0,
            'color_libre' => null,
            'addons' => [['addon_id' => $grabado->id, 'name' => 'Grabado', 'price' => 300, 'custom_text' => 'Feliz 2026']],
            'addons_total' => 300.0,
            'cantidad' => 10, 'precio_unitario' => 1170.0, 'base' => 720.0, 'subtotal' => 11700.0,
        ]], $this->resumenOrden($o)['items']);

        // Con variante, el stock que baja es el de la variante; el del producto queda igual.
        $this->assertSame(10, $rojo->fresh()->stock);
        $this->assertSame(99, $p->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $p->id, 'product_variant_id' => $rojo->id, 'cantidad' => -10]);

        $this->assertStringContainsString(
            "• Chispa 2m\n"
            ."  Color: Rojo\n"
            ."  Recargo color: +\$150\n"
            ."  Grabado: \"Feliz 2026\" (+\$300)\n"
            ."  Cantidad: 10\n"
            ."  Precio original: \$800\n"
            ."  Precio final: \$720\n"
            ."  ¡Ahorrás 10%!\n"
            ."  Precio unitario con opciones: \$1.170\n"
            ."  Subtotal: \$11.700\n\n",
            $o->mensaje_whatsapp
        );
    }

    public function test_oferta_de_monto_fijo_sobre_el_precio_de_lista(): void
    {
        $p = $this->producto(['title' => 'Volcán', 'price' => 1000, 'stock' => 10]);
        ProductOffer::create([
            'product_id' => $p->id, 'tipo_descuento' => TipoDescuento::Fijo, 'valor_descuento' => 250,
            'alcance' => AlcanceOferta::Todos, 'is_active' => true,
        ]);

        $this->checkout([['product_id' => $p->id, 'quantity' => 2]])
            ->assertOk()->assertJson(['subtotal' => 1500.0, 'total' => 1500.0]);

        $item = Order::with('items')->sole()->items->sole();
        $this->assertEquals(750.0, (float) $item->precio_unitario);
        $this->assertEquals(750.0, (float) $item->base_unit_price);
        $this->assertStringContainsString(
            "  Precio original: \$1.000\n  Precio final: \$750\n  ¡Ahorrás 25%!\n",
            Order::sole()->mensaje_whatsapp
        );
    }

    // --- color "a elección del cliente" ----------------------------------------------

    public function test_variante_a_eleccion_del_cliente_guarda_el_color_libre_y_no_toca_stock_ilimitado(): void
    {
        $p = $this->producto(['title' => 'Bengala', 'price' => 500, 'stock' => 30]);
        $libre = ProductVariant::factory()->create([
            'product_id' => $p->id, 'name' => 'A elección', 'is_custom_color' => true, 'price_addon' => 0, 'stock' => null,
        ]);

        $this->checkout([[
            'product_id' => $p->id, 'quantity' => 2, 'variant_id' => $libre->id, 'custom_color_text' => 'Verde agua',
        ]])->assertOk()->assertJson(['total' => 1000.0]);

        $o = Order::with('items')->sole();
        $item = $o->items->sole();
        $this->assertSame('A elección', $item->variant_name);
        $this->assertSame('Verde agua', $item->custom_color_text);
        $this->assertStringContainsString("  Color solicitado: Verde agua\n", $o->mensaje_whatsapp);
        $this->assertStringNotContainsString('Recargo color', $o->mensaje_whatsapp);

        // Variante ilimitada (stock null): ni baja el stock ni registra movimiento.
        $this->assertNull($libre->fresh()->stock);
        $this->assertSame(30, $p->fresh()->stock);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    // --- combos -----------------------------------------------------------------

    /**
     * @return array{combo: Combo, a: Product, b: Product, rojo: ProductVariant, azul: ProductVariant}
     */
    private function combo(bool $envioGratis, string $titulo = 'Combo Fiesta', float $precio = 5000): array
    {
        $a = $this->producto(['title' => 'Bengala', 'price' => 1000, 'stock' => 10]);
        $b = $this->producto(['title' => 'Volcán', 'price' => 500, 'stock' => 99]);
        $rojo = ProductVariant::factory()->create(['product_id' => $b->id, 'name' => 'Rojo', 'price_addon' => 200, 'stock' => 8]);
        $azul = ProductVariant::factory()->create(['product_id' => $b->id, 'name' => 'Azul', 'price_addon' => 0, 'stock' => 6]);

        $combo = Combo::create([
            'title' => $titulo, 'description' => 'Para tu evento', 'price' => $precio,
            'is_free_shipping' => $envioGratis, 'is_active' => true,
        ]);
        $combo->items()->create(['product_id' => $a->id, 'quantity' => 1, 'sort_order' => 0]);
        $combo->items()->create(['product_id' => $b->id, 'quantity' => 2, 'sort_order' => 1]);

        return compact('combo', 'a', 'b', 'rojo', 'azul');
    }

    public function test_combo_fijo_sin_colores_a_elegir_cobra_el_precio_del_combo(): void
    {
        // Combo cuyos componentes no tienen variantes: no hay nada que elegir.
        $a = $this->producto(['title' => 'Bengala', 'price' => 1000, 'stock' => 10]);
        $b = $this->producto(['title' => 'Volcán', 'price' => 500, 'stock' => 99]);
        $combo = Combo::create(['title' => 'Combo Simple', 'price' => 1800, 'is_free_shipping' => false, 'is_active' => true]);
        $combo->items()->create(['product_id' => $a->id, 'quantity' => 1, 'sort_order' => 0]);
        $combo->items()->create(['product_id' => $b->id, 'quantity' => 2, 'sort_order' => 1]);

        $this->checkout([['combo_id' => $combo->id, 'quantity' => 3, 'component_selections' => []]])
            ->assertOk()->assertJson(['subtotal' => 5400.0, 'total' => 5400.0, 'itemCount' => 1]);

        $o = Order::with('items')->sole();
        $this->assertFalse((bool) $o->free_shipping);

        $item = $o->items->sole();
        $this->assertNull($item->product_id);
        $this->assertSame($combo->id, $item->combo_id);
        $this->assertSame('Combo Simple', $item->product_title);
        $this->assertSame(3, $item->cantidad);
        $this->assertEquals(1800.0, (float) $item->precio_unitario);
        $this->assertEquals(5400.0, (float) $item->subtotal);
        $this->assertSame([
            ['product_id' => $a->id, 'product_variant_id' => null, 'quantity' => 1, 'product_title' => 'Bengala', 'variant_name' => null, 'variant_color_hex' => null, 'custom_color_text' => null],
            ['product_id' => $b->id, 'product_variant_id' => null, 'quantity' => 2, 'product_title' => 'Volcán', 'variant_name' => null, 'variant_color_hex' => null, 'custom_color_text' => null],
        ], $item->combo_selections);

        // Stock: cantidad del componente × cantidad de combos.
        $this->assertSame(7, $a->fresh()->stock);
        $this->assertSame(93, $b->fresh()->stock);
        $this->assertStringContainsString("🎁 *Combo: Combo Simple*\n  - 1x Bengala\n  - 2x Volcán\n  Cantidad: 3\n  Precio combo: \$1.800\n  Subtotal: \$5.400\n\n", $o->mensaje_whatsapp);
        $this->assertStringNotContainsString('Envío gratis', $o->mensaje_whatsapp);
    }

    public function test_combo_con_eleccion_de_color_y_envio_gratis(): void
    {
        $ctx = $this->combo(envioGratis: true);

        $this->checkout([[
            'combo_id' => $ctx['combo']->id, 'quantity' => 2,
            'component_selections' => [
                ['product_id' => $ctx['a']->id, 'variant_id' => null, 'custom_color_text' => null],
                ['product_id' => $ctx['b']->id, 'variant_id' => $ctx['rojo']->id, 'custom_color_text' => null],
            ],
        ]])->assertOk()->assertJson(['subtotal' => 10000.0, 'total' => 10000.0]);

        $o = Order::with('items')->sole();
        $this->assertTrue((bool) $o->free_shipping);

        $item = $o->items->sole();
        // El recargo del color NO se suma en un combo: el precio es el fijo del combo.
        $this->assertEquals(5000.0, (float) $item->precio_unitario);
        $this->assertEquals(10000.0, (float) $item->subtotal);
        $this->assertSame(
            ['product_id' => $ctx['b']->id, 'product_variant_id' => $ctx['rojo']->id, 'quantity' => 2, 'product_title' => 'Volcán', 'variant_name' => 'Rojo', 'variant_color_hex' => $ctx['rojo']->color_hex, 'custom_color_text' => null],
            $item->combo_selections[1]
        );

        // 2 combos: Bengala 1×2 = 2 del producto; Volcán 2×2 = 4 de la variante Rojo.
        $this->assertSame(8, $ctx['a']->fresh()->stock);
        $this->assertSame(4, $ctx['rojo']->fresh()->stock);
        $this->assertSame(6, $ctx['azul']->fresh()->stock);
        $this->assertSame(99, $ctx['b']->fresh()->stock);

        $this->assertStringContainsString("  - 2x Volcán (Color: Rojo)\n", $o->mensaje_whatsapp);
        $this->assertStringContainsString("  🚚 Envío gratis incluido\n", $o->mensaje_whatsapp);
        $this->assertStringContainsString("🚚 *¡Envío gratis incluido por tu combo!*\n\n", $o->mensaje_whatsapp);
    }

    public function test_combo_y_producto_en_el_mismo_pedido_con_cupon_y_plan_de_tarjeta(): void
    {
        $ctx = $this->combo(envioGratis: false);
        $suelto = $this->producto(['title' => 'Maletín', 'price' => 2500, 'stock' => 4]);
        $codigo = $this->codigo(['code' => 'VERANO10', 'percentage' => 10]);
        $plan = CardPaymentPlan::factory()->create(['name' => '3 cuotas', 'installments' => 3, 'surcharge_percentage' => 20]);

        $r = $this->checkout(
            [
                ['product_id' => $suelto->id, 'quantity' => 2],
                [
                    'combo_id' => $ctx['combo']->id, 'quantity' => 1,
                    'component_selections' => [['product_id' => $ctx['b']->id, 'variant_id' => $ctx['azul']->id, 'custom_color_text' => null]],
                ],
            ],
            [
                'cart_discount_code' => 'verano10',
                'cart_payment_plan' => ['id' => $plan->id, 'name' => '3 cuotas', 'installments' => 3, 'surcharge_percentage' => 20.0],
            ],
        );

        // subtotal = 2×2500 + 5000 = 10000; cupón -10% = 1000; total 9000; recargo 20% = 1800 → 10800 (3 × 3600)
        $r->assertOk()->assertJson([
            'subtotal' => 10000.0,
            'discount_amount' => 1000.0,
            'total' => 9000.0,
            'itemCount' => 2,
            'payment_plan' => [
                'id' => $plan->id, 'name' => '3 cuotas', 'installments' => 3, 'surcharge_percentage' => 20.0,
                'surcharge_amount' => 1800.0, 'total_with_surcharge' => 10800.0, 'installment_amount' => 3600.0,
            ],
        ]);

        $o = Order::with('items')->sole();
        $resumen = $this->resumenOrden($o);
        $this->assertSame('VERANO10', $resumen['discount_code']);
        $this->assertSame($codigo->id, $o->discount_code_id);
        $this->assertSame(
            ['subtotal' => 10000.0, 'discount_amount' => 1000.0, 'total' => 9000.0, 'free_shipping' => false],
            array_intersect_key($resumen, array_flip(['subtotal', 'discount_amount', 'total', 'free_shipping']))
        );
        $this->assertSame(
            ['plan' => '3 cuotas', 'cuotas' => 3, 'recargo_pct' => 20.0, 'recargo' => 1800.0, 'total_con_recargo' => 10800.0],
            array_intersect_key($resumen, array_flip(['plan', 'cuotas', 'recargo_pct', 'recargo', 'total_con_recargo']))
        );
        $this->assertSame($plan->id, $o->card_payment_plan_id);
        $this->assertSame(2, $o->items->count());

        $this->assertSame(1, $codigo->fresh()->usage_count);
        $this->assertSame(2, $suelto->fresh()->stock);
        // Volcán ×2 por 1 combo, en azul: 6 − 2.
        $this->assertSame(4, $ctx['azul']->fresh()->stock);

        $this->assertStringContainsString(
            "🏷️ *Código de descuento: VERANO10*\n  Subtotal: \$10.000\n  Descuento (10%): -\$1.000\n\n",
            $o->mensaje_whatsapp
        );
        $this->assertStringContainsString(
            "💰 *TOTAL: \$9.000*\n\n💳 *Forma de pago: Tarjeta de crédito* — 3 cuotas sin interés mensual, recargo 20%\n"
            ."Total a cobrar: \$10.800 (\$3.600 c/u)\n👉 Generar link de pago en Mercado Pago por \$10.800",
            $o->mensaje_whatsapp
        );

        $this->assertNull(session('cart'));
        $this->assertNull(session('cart_discount_code'));
        $this->assertNull(session('cart_payment_plan'));
    }

    // --- envío gratis por umbral (informativo) ------------------------------------------

    public function test_envio_gratis_por_umbral_es_solo_informativo_en_el_mensaje(): void
    {
        Setting::set('free_shipping_threshold', 5000);
        $p = $this->producto(['price' => 3000, 'stock' => 10]);

        $this->checkout([['product_id' => $p->id, 'quantity' => 1]])->assertOk();
        $o = Order::sole();
        $this->assertFalse((bool) $o->free_shipping);
        $this->assertStringContainsString("🚚 Le faltan \$2.000 para alcanzar el envío gratis.\n\n", $o->mensaje_whatsapp);
        $o->delete();

        $this->checkout([['product_id' => $p->id, 'quantity' => 2]])->assertOk();
        $o = Order::sole();
        $this->assertFalse((bool) $o->free_shipping);
        $this->assertStringContainsString("🚚 *¡Envío gratis alcanzado!*\n\n", $o->mensaje_whatsapp);
    }

    // --- cupón -----------------------------------------------------------------

    public function test_cupon_porcentual_descuenta_sobre_el_subtotal_y_consume_un_uso(): void
    {
        $p = $this->producto(['price' => 1000, 'stock' => 10]);
        $codigo = $this->codigo(['percentage' => 15, 'usage_limit' => 5]);

        $this->checkout([['product_id' => $p->id, 'quantity' => 3]], ['cart_discount_code' => 'VERANO10'])
            ->assertOk()->assertJson(['subtotal' => 3000.0, 'discount_amount' => 450.0, 'total' => 2550.0]);

        $o = Order::sole();
        $this->assertEquals(450.0, (float) $o->discount_amount);
        $this->assertEquals(2550.0, (float) $o->total);
        $this->assertSame(1, $codigo->fresh()->usage_count);
    }

    public function test_cupon_que_no_alcanza_el_minimo_rechaza_el_pedido_y_lo_quita_de_la_sesion(): void
    {
        $p = $this->producto(['price' => 1000, 'stock' => 10]);
        $codigo = $this->codigo(['min_purchase_amount' => 5000]);

        $this->checkout([['product_id' => $p->id, 'quantity' => 1]], ['cart_discount_code' => 'VERANO10'])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'El código "VERANO10" requiere una compra mínima de $5,000.00 (subtotal actual: $1,000.00). Lo quitamos de tu carrito, por favor reintentá.');

        $this->assertSame(0, Order::count());
        $this->assertSame(10, $p->fresh()->stock);
        $this->assertSame(0, $codigo->fresh()->usage_count);
        $this->assertNull(session('cart_discount_code'));
        $this->assertNotNull(session('cart')); // el carrito sigue ahí
    }

    public function test_cupon_agotado_rechaza_el_pedido(): void
    {
        $p = $this->producto(['price' => 1000, 'stock' => 10]);
        $this->codigo(['usage_limit' => 1, 'usage_count' => 1]);

        $this->checkout([['product_id' => $p->id, 'quantity' => 1]], ['cart_discount_code' => 'VERANO10'])
            ->assertStatus(422)->assertJson(['success' => false]);

        $this->assertSame(0, Order::count());
        $this->assertSame(10, $p->fresh()->stock);
    }

    // --- plan de tarjeta -------------------------------------------------------------

    public function test_plan_de_tarjeta_desactivado_se_descarta_sin_abortar_el_pedido(): void
    {
        $p = $this->producto(['price' => 1000, 'stock' => 10]);
        $plan = CardPaymentPlan::factory()->inactive()->create(['installments' => 6, 'surcharge_percentage' => 35]);

        $this->checkout(
            [['product_id' => $p->id, 'quantity' => 1]],
            ['cart_payment_plan' => ['id' => $plan->id, 'name' => 'x', 'installments' => 6, 'surcharge_percentage' => 35.0]]
        )->assertOk()->assertJson(['total' => 1000.0, 'payment_plan' => null]);

        $o = Order::sole();
        $this->assertNull($o->card_payment_plan_id);
        $this->assertNull($o->total_with_surcharge);
        $this->assertStringNotContainsString('Mercado Pago', $o->mensaje_whatsapp);
    }

    public function test_plan_de_un_pago_dice_pago_unico_y_no_muestra_valor_de_cuota(): void
    {
        $p = $this->producto(['price' => 1000, 'stock' => 10]);
        $plan = CardPaymentPlan::factory()->create(['name' => '1 pago', 'installments' => 1, 'surcharge_percentage' => 10]);

        $this->checkout(
            [['product_id' => $p->id, 'quantity' => 1]],
            ['cart_payment_plan' => ['id' => $plan->id, 'name' => '1 pago', 'installments' => 1, 'surcharge_percentage' => 10.0]]
        )->assertOk()->assertJson(['payment_plan' => ['surcharge_amount' => 100.0, 'total_with_surcharge' => 1100.0]]);

        $mensaje = Order::sole()->mensaje_whatsapp;
        $this->assertStringContainsString("pago único, recargo 10%\nTotal a cobrar: \$1.100\n👉 Generar link de pago en Mercado Pago por \$1.100", $mensaje);
    }

    // --- rechazos ---------------------------------------------------------------

    public function test_carrito_vacio_responde_422(): void
    {
        $this->postJson(route('cart.whatsapp'), ['customer_data' => $this->datosCliente()])
            ->assertStatus(422)
            ->assertExactJson(['success' => false, 'message' => 'El carrito está vacío.']);
    }

    public function test_producto_con_colores_activos_sin_elegir_color_rechaza_el_pedido(): void
    {
        $p = $this->producto(['title' => 'Chispa 2m', 'stock' => 10]);
        ProductVariant::factory()->create(['product_id' => $p->id, 'name' => 'Rojo', 'stock' => 5]);

        $this->checkout([['product_id' => $p->id, 'quantity' => 1]])
            ->assertStatus(422)
            ->assertExactJson(['success' => false, 'message' => 'Elegí un color para «Chispa 2m» antes de finalizar el pedido.']);

        $this->assertSame(0, Order::count());
    }

    public function test_color_a_eleccion_sin_texto_rechaza_el_pedido(): void
    {
        $p = $this->producto(['title' => 'Bengala', 'stock' => 10]);
        $libre = ProductVariant::factory()->create(['product_id' => $p->id, 'is_custom_color' => true, 'stock' => null]);

        $this->checkout([['product_id' => $p->id, 'quantity' => 1, 'variant_id' => $libre->id]])
            ->assertStatus(422)
            ->assertExactJson(['success' => false, 'message' => 'Indicá el color que querés para «Bengala» antes de finalizar el pedido.']);
    }

    public function test_addon_con_texto_obligatorio_sin_texto_rechaza_el_pedido(): void
    {
        $p = $this->producto(['title' => 'Chispa 2m', 'stock' => 10]);
        $grabado = Addon::factory()->conTexto()->create(['name' => 'Grabado']);
        $p->addons()->attach($grabado->id);

        $this->checkout([['product_id' => $p->id, 'quantity' => 1, 'addon_selections' => [['addon_id' => $grabado->id, 'custom_text' => null]]]])
            ->assertStatus(422)
            ->assertExactJson(['success' => false, 'message' => 'Completá el texto de «Grabado» para «Chispa 2m» antes de finalizar el pedido.']);
    }

    public function test_variante_desactivada_despues_de_armar_el_carrito_aborta_el_pedido(): void
    {
        $p = $this->producto(['stock' => 10]);
        $rojo = ProductVariant::factory()->create(['product_id' => $p->id, 'name' => 'Rojo', 'stock' => 5]);
        // Hay otra variante activa, así que el producto sigue exigiendo color.
        ProductVariant::factory()->create(['product_id' => $p->id, 'name' => 'Azul', 'stock' => 5]);
        $rojo->update(['is_active' => false]);

        $this->checkout([['product_id' => $p->id, 'quantity' => 1, 'variant_id' => $rojo->id]])
            ->assertStatus(422)
            ->assertExactJson(['success' => false, 'message' => 'Algunas opciones de tu carrito ya no están disponibles. Actualizá el carrito e intentá nuevamente.']);

        $this->assertSame(0, Order::count());
    }

    public function test_datos_del_cliente_incompletos_devuelven_errores_de_validacion(): void
    {
        $p = $this->producto(['stock' => 10]);

        $this->checkout([['product_id' => $p->id, 'quantity' => 1]], [], ['email' => 'no-es-un-email', 'dni' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['customer_data.email', 'customer_data.dni']);

        $this->assertSame(0, Order::count());
        $this->assertSame(10, $p->fresh()->stock);
    }

    public function test_stock_insuficiente_responde_con_el_detalle_y_no_crea_nada(): void
    {
        $p = $this->producto(['title' => 'Chispa 2m', 'stock' => 2]);
        $otro = $this->producto(['title' => 'Otro', 'price' => 100, 'stock' => 10]);

        $this->checkout([
            ['product_id' => $otro->id, 'quantity' => 1],
            ['product_id' => $p->id, 'quantity' => 5],
        ])->assertStatus(422)->assertExactJson([
            'success' => false,
            'message' => 'Algunos productos de tu carrito ya no tienen stock suficiente.',
            'stock_insuficiente' => [[
                'product_id' => $p->id, 'product_title' => 'Chispa 2m', 'cantidad_solicitada' => 5, 'stock_disponible' => 2,
            ]],
        ]);

        $this->assertSame(0, Order::count());
        $this->assertSame(2, $p->fresh()->stock);
        $this->assertSame(10, $otro->fresh()->stock);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertNotNull(session('cart'));
    }

    public function test_stock_insuficiente_en_un_componente_de_combo_rechaza_el_pedido(): void
    {
        $ctx = $this->combo(envioGratis: false);
        $ctx['rojo']->update(['stock' => 3]); // el combo pide 2 por unidad; con 2 combos hacen falta 4

        $this->checkout([[
            'combo_id' => $ctx['combo']->id, 'quantity' => 2,
            'component_selections' => [['product_id' => $ctx['b']->id, 'variant_id' => $ctx['rojo']->id, 'custom_color_text' => null]],
        ]])->assertStatus(422)->assertJson([
            'success' => false,
            'stock_insuficiente' => [['product_id' => $ctx['b']->id, 'product_title' => 'Volcán', 'cantidad_solicitada' => 4, 'stock_disponible' => 3]],
        ]);

        $this->assertSame(0, Order::count());
        $this->assertSame(3, $ctx['rojo']->fresh()->stock);
        $this->assertSame(10, $ctx['a']->fresh()->stock);
    }

    public function test_producto_borrado_se_descarta_del_carrito_y_el_resto_se_compra(): void
    {
        $p = $this->producto(['price' => 1000, 'stock' => 10]);

        $this->checkout([
            ['product_id' => 999999, 'quantity' => 4],
            ['product_id' => $p->id, 'quantity' => 1],
        ])->assertOk()->assertJson(['total' => 1000.0, 'itemCount' => 1]);

        $this->assertSame(1, Order::sole()->items()->count());
    }

    public function test_carrito_con_formato_viejo_se_sigue_comprando(): void
    {
        $p = $this->producto(['price' => 1000, 'stock' => 10]);

        $this->checkout([$p->id => 2])->assertOk()->assertJson(['total' => 2000.0]);

        $this->assertSame(8, $p->fresh()->stock);
    }
}
