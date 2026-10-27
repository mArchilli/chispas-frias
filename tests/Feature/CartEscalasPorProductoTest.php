<?php

namespace Tests\Feature;

use App\Enums\AlcanceOferta;
use App\Enums\TipoDescuento;
use App\Models\Category;
use App\Models\Combo;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPriceTier;
use App\Models\ProductVariant;
use App\Services\CalculadoraPedido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CHK-2c2 — El escalón de precio por cantidad se resuelve por PRODUCTO, sumando las unidades de todas sus líneas (otros colores,
 * otras personalizaciones), igual que Chisperío: 10 rojas + 10 azules del mismo producto pagan el escalón de 20. El mismo precio
 * lo ven el carrito, el drawer y el checkout, y es el que se guarda en la orden. Las unidades dentro de un combo no suman.
 */
class CartEscalasPorProductoTest extends TestCase
{
    use RefreshDatabase;

    /** Producto de $100 con escalas: 10 → $90, 20 → $80. */
    private function producto(array $overrides = []): Product
    {
        $producto = Product::factory()->for(Category::factory()->create())->create(array_merge([
            'price' => 100, 'stock' => 500, 'is_active' => true,
        ], $overrides));

        ProductPriceTier::factory()->create(['product_id' => $producto->id, 'cantidad_minima' => 10, 'precio_unitario' => 90]);
        ProductPriceTier::factory()->create(['product_id' => $producto->id, 'cantidad_minima' => 20, 'precio_unitario' => 80]);

        return $producto;
    }

    private function color(Product $producto, string $nombre, float $recargo = 0, bool $activo = true): ProductVariant
    {
        return ProductVariant::factory()->create([
            'product_id' => $producto->id, 'name' => $nombre, 'price_addon' => $recargo, 'stock' => 500, 'is_active' => $activo,
        ]);
    }

    private function linea(Product $producto, ProductVariant $color, int $cantidad): array
    {
        return [
            'line_key' => "v{$color->id}",
            'product_id' => $producto->id,
            'quantity' => $cantidad,
            'variant_id' => $color->id,
            'addon_selections' => [],
            'custom_color_text' => null,
        ];
    }

    /** @return array<string, array<string, mixed>> Los ítems del carrito, por id de color (v<id>) o de combo (c<id>): el line_key lo genera el servidor. */
    private function items(array $carrito, array $sesion = []): array
    {
        $items = $this->withSession(array_merge(['cart' => $carrito], $sesion))
            ->getJson(route('cart.preview'))
            ->assertOk()
            ->json('cartItems');

        return collect($items)->keyBy(fn (array $item) => isset($item['combo']) ? 'c'.$item['combo']['id'] : 'v'.$item['variant']['id'])->all();
    }

    public function test_el_mismo_producto_en_dos_colores_cruza_el_escalon_con_la_suma(): void
    {
        $producto = $this->producto();
        $roja = $this->color($producto, 'Roja');
        $azul = $this->color($producto, 'Azul');

        // Cada línea sola (10 unidades) pagaría el escalón de 10; juntas son 20 y las dos pagan el de 20.
        $solas = $this->items([$this->linea($producto, $roja, 10)]);
        $this->assertEquals(90, $solas["v{$roja->id}"]['unit_price']);

        $items = $this->items([$this->linea($producto, $roja, 10), $this->linea($producto, $azul, 10)]);

        foreach ([$roja, $azul] as $color) {
            $item = $items["v{$color->id}"];
            $this->assertEquals(80, $item['unit_price']);
            $this->assertEquals(80, $item['price']);
            $this->assertEquals(800, $item['subtotal']);
            $this->assertSame(20, $item['unidades_del_producto']);
        }

        $this->withSession(['cart' => [$this->linea($producto, $roja, 10), $this->linea($producto, $azul, 10)]])
            ->get(route('cart.index'))
            ->assertInertia(fn ($page) => $page->where('subtotal', 1600)->where('total', 1600));
    }

    public function test_el_recargo_del_color_se_suma_despues_del_escalon_de_cada_linea(): void
    {
        $producto = $this->producto();
        $roja = $this->color($producto, 'Roja');
        $dorada = $this->color($producto, 'Dorada', 30);

        $items = $this->items([$this->linea($producto, $roja, 10), $this->linea($producto, $dorada, 10)]);

        $this->assertEquals(80, $items["v{$roja->id}"]['unit_price']);
        $this->assertEquals(110, $items["v{$dorada->id}"]['unit_price']);
    }

    public function test_la_oferta_se_aplica_sobre_el_precio_del_escalon_por_producto(): void
    {
        $producto = $this->producto();
        $producto->offers()->create([
            'offer_price' => 0, 'is_active' => true, 'tipo_descuento' => TipoDescuento::Porcentaje,
            'valor_descuento' => 10, 'alcance' => AlcanceOferta::Todos,
        ]);
        $roja = $this->color($producto, 'Roja');
        $azul = $this->color($producto, 'Azul');

        $items = $this->items([$this->linea($producto, $roja, 10), $this->linea($producto, $azul, 10)]);

        // Escalón de 20 ($80) con el 10 % de la oferta.
        $this->assertEquals(72, $items["v{$roja->id}"]['unit_price']);
        $this->assertEquals(80, $items["v{$roja->id}"]['list_price']);
        $this->assertEquals(72, $items["v{$azul->id}"]['unit_price']);
    }

    public function test_el_cupon_descuenta_sobre_el_subtotal_que_ya_tiene_el_escalon_por_producto(): void
    {
        $producto = $this->producto();
        $roja = $this->color($producto, 'Roja');
        $azul = $this->color($producto, 'Azul');
        DiscountCode::create(['code' => 'DIEZ', 'percentage' => 10, 'is_active' => true, 'usage_count' => 0]);

        $carrito = [$this->linea($producto, $roja, 10), $this->linea($producto, $azul, 10)];

        $this->withSession(['cart' => $carrito, 'cart_discount_code' => 'DIEZ'])
            ->get(route('cart.index'))
            ->assertInertia(fn ($page) => $page->where('subtotal', 1600)->where('total', 1440));

        $this->withSession(['cart' => $carrito, 'cart_discount_code' => 'DIEZ'])
            ->postJson(route('cart.whatsapp'), ['customer_data' => ['name' => 'Juana', 'phone' => '1122334455', 'province' => 'buenos-aires']])
            ->assertOk()
            ->assertJson(['total' => 1440]);
    }

    public function test_las_unidades_dentro_de_un_combo_no_suman_al_escalon_del_producto_suelto(): void
    {
        $producto = $this->producto();
        $roja = $this->color($producto, 'Roja');
        $combo = Combo::create(['title' => 'Combo', 'price' => 1000, 'is_free_shipping' => false, 'is_active' => true]);
        $combo->items()->create(['product_id' => $producto->id, 'quantity' => 12, 'sort_order' => 0]);

        // 5 sueltas + 12 dentro del combo: si el combo sumara serían 17 (escalón de 10); no suma, así que valen $100.
        $items = $this->items([
            $this->linea($producto, $roja, 5),
            ['line_key' => 'c', 'combo_id' => $combo->id, 'quantity' => 1, 'component_selections' => [['product_id' => $producto->id, 'variant_id' => $roja->id, 'custom_color_text' => null]]],
        ]);

        $this->assertEquals(100, $items["v{$roja->id}"]['unit_price']);
        $this->assertSame(5, $items["v{$roja->id}"]['unidades_del_producto']);
        $this->assertEquals(1000, $items["c{$combo->id}"]['unit_price']);
        $this->assertEquals(1500, app(CalculadoraPedido::class)->subtotal(collect($items)));
    }

    public function test_quitar_una_linea_devuelve_las_otras_al_escalon_anterior(): void
    {
        $producto = $this->producto();
        $roja = $this->color($producto, 'Roja');
        $azul = $this->color($producto, 'Azul');
        $claveAzul = $this->items([$this->linea($producto, $roja, 10), $this->linea($producto, $azul, 10)])["v{$azul->id}"]["line_key"];

        // (la sesión del paso anterior sigue viva: items() la dejó cargada)
        $this->assertEquals(80, $this->getJson(route('cart.preview'))->json('cartItems.0.unit_price'));

        $this->deleteJson(route('cart.remove'), ['line_key' => $claveAzul])->assertOk();

        $this->getJson(route('cart.preview'))
            ->assertJsonPath('cartItems.0.unit_price', 90)
            ->assertJsonPath('subtotal', 900);
    }

    public function test_cambiar_la_cantidad_de_una_linea_reprecia_a_las_otras_y_el_json_trae_su_subtotal_real(): void
    {
        $producto = $this->producto();
        $roja = $this->color($producto, 'Roja');
        $azul = $this->color($producto, 'Azul');

        $claveAzul = $this->items([$this->linea($producto, $roja, 10), $this->linea($producto, $azul, 5)])["v{$azul->id}"]['line_key'];

        // 10 + 5 = 15 unidades → escalón de 10.
        $this->getJson(route('cart.preview'))->assertJsonPath('subtotal', 1350);

        // Con 15 azules son 25 → escalón de 20, y el subtotal de la línea que se tocó ya lo trae con ese precio.
        $this->patchJson(route('cart.update'), ['line_key' => $claveAzul, 'quantity' => 15])
            ->assertOk()
            ->assertJsonPath('subtotal', 1200);

        $this->getJson(route('cart.preview'))
            ->assertJsonPath('cartItems.0.unit_price', 80)
            ->assertJsonPath('subtotal', 2000);
    }

    public function test_una_linea_con_el_color_desactivado_no_cuenta_para_el_escalon(): void
    {
        $producto = $this->producto();
        $roja = $this->color($producto, 'Roja');
        $vieja = $this->color($producto, 'Vieja', 0, activo: false);

        $items = $this->items([$this->linea($producto, $roja, 10), $this->linea($producto, $vieja, 10)]);

        // La vieja se descarta en el carrito (no se cobra): sus 10 unidades tampoco empujan el escalón de la roja.
        $this->assertCount(1, $items);
        $this->assertEquals(90, $items["v{$roja->id}"]['unit_price']);
    }

    public function test_el_total_de_la_orden_y_sus_lineas_son_lo_que_mostraba_el_carrito(): void
    {
        $producto = $this->producto();
        $roja = $this->color($producto, 'Roja');
        $azul = $this->color($producto, 'Azul');
        $carrito = [$this->linea($producto, $roja, 12), $this->linea($producto, $azul, 8)];

        $visto = $this->items($carrito);
        $subtotalVisto = collect($visto)->sum('subtotal');
        $this->assertEquals(1600, $subtotalVisto);

        $respuesta = $this->withSession(['cart' => $carrito])
            ->postJson(route('cart.whatsapp'), ['customer_data' => ['name' => 'Juana', 'phone' => '1122334455', 'province' => 'buenos-aires']])
            ->assertOk()
            ->assertJson(['total' => 1600]);

        $orden = Order::with('items')->findOrFail($respuesta->json('order_id'));
        $this->assertEquals(1600, $orden->total);

        foreach ($orden->items as $item) {
            $this->assertEquals(80, $item->precio_unitario);
            $this->assertEquals($visto[$item->product_variant_id === $roja->id ? "v{$roja->id}" : "v{$azul->id}"]['subtotal'], $item->subtotal);
        }
    }

    public function test_suma_n_mas_cuenta_por_producto_y_promete_el_precio_que_cobra_el_checkout(): void
    {
        $producto = $this->producto();
        $roja = $this->color($producto, 'Roja');
        $dorada = $this->color($producto, 'Dorada', 30);
        $claveRoja = "v{$roja->id}";
        $claveDorada = "v{$dorada->id}";

        // 6 + 6 = 12 unidades (escalón de 10): para el de 20 faltan 8, no 14.
        $items = $this->items([$this->linea($producto, $roja, 6), $this->linea($producto, $dorada, 6)]);
        $this->assertEquals(['faltan' => 8, 'precio_unitario' => 80.0], $items[$claveRoja]['siguiente_escala']);
        $this->assertEquals(['faltan' => 8, 'precio_unitario' => 110.0], $items[$claveDorada]['siguiente_escala']);

        // Se suman justo las que faltan (en la roja): lo prometido es exactamente el precio que cobra el servidor.
        $despues = $this->items([$this->linea($producto, $roja, 14), $this->linea($producto, $dorada, 6)]);
        $this->assertEquals(80, $despues[$claveRoja]['unit_price']);
        $this->assertEquals(110, $despues[$claveDorada]['unit_price']);
        $this->assertNull($despues[$claveRoja]['siguiente_escala']);
    }

    public function test_el_drawer_y_la_pagina_del_carrito_traen_los_mismos_precios(): void
    {
        $producto = $this->producto();
        $roja = $this->color($producto, 'Roja');
        $azul = $this->color($producto, 'Azul');
        $carrito = [$this->linea($producto, $roja, 10), $this->linea($producto, $azul, 10)];

        $drawer = $this->withSession(['cart' => $carrito])->getJson(route('cart.preview'))->json();

        $this->withSession(['cart' => $carrito])
            ->get(route('cart.index'))
            ->assertInertia(fn ($page) => $page
                ->where('subtotal', $drawer['subtotal'])
                ->where('cartItems.0.unit_price', $drawer['cartItems'][0]['unit_price'])
                ->where('cartItems.1.unit_price', $drawer['cartItems'][1]['unit_price']));

        $this->withSession(['cart' => $carrito])
            ->get(route('cart.checkout'))
            ->assertInertia(fn ($page) => $page
                ->where('subtotal', $drawer['subtotal'])
                ->where('cartItems.0.unit_price', 80)
                ->where('cartItems.1.unit_price', 80));
    }
}
