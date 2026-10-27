<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\Category;
use App\Models\Combo;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * CHK-2c — aviso de stock ANTES del checkout, calculado en el servidor contra las columnas locales de stock (el espejo del
 * Central): el carrito (página y drawer) y el checkout ajustan la cantidad a lo que queda y avisan; una línea agotada se marca y
 * conserva su cantidad; lo ilimitado no se limita; las líneas que comparten existencia se reparten el disponible.
 */
class CartStockAvisoTest extends TestCase
{
    use RefreshDatabase;

    private function producto(array $overrides = []): Product
    {
        return Product::factory()->for(Category::factory()->create())->create(array_merge([
            'price' => 1000,
            'stock' => 50,
            'is_active' => true,
        ], $overrides));
    }

    private function linea(Product $producto, int $cantidad, ?ProductVariant $variante = null, array $addonIds = []): array
    {
        $selecciones = array_map(fn (int $id) => ['addon_id' => $id, 'custom_text' => null], $addonIds);
        $claveAddons = implode(',', $addonIds);

        return [
            'line_key' => "p{$producto->id}:v".($variante?->id ?? 0).":a{$claveAddons}",
            'product_id' => $producto->id,
            'quantity' => $cantidad,
            'variant_id' => $variante?->id,
            'addon_selections' => $selecciones,
            'custom_color_text' => null,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function itemsDelCarrito(array $carrito): array
    {
        return $this->withSession(['cart' => $carrito])->get(route('cart.index'))
            ->viewData('page')['props']['cartItems'];
    }

    public function test_baja_la_cantidad_al_disponible_y_avisa(): void
    {
        $producto = $this->producto(['stock' => 3]);

        $this->withSession(['cart' => [$this->linea($producto, 5)]])
            ->get(route('cart.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cart/Index')
                ->where('cartItems.0.quantity', 3)
                ->where('cartItems.0.subtotal', 3000)
                ->where('cartItems.0.stock_info.sin_stock', false)
                ->where('cartItems.0.stock_info.tope', 3)
                ->where('cartItems.0.stock_info.aviso', ['de' => 5, 'a' => 3])
                ->where('total', 3000)
            );

        // Lo ajustado queda en la sesión: el checkout trabaja con la misma cantidad.
        $this->assertSame(3, session('cart')[0]['quantity']);
    }

    public function test_el_aviso_sigue_en_la_recarga_pero_se_va_si_el_cliente_cambia_la_cantidad(): void
    {
        $producto = $this->producto(['stock' => 3]);
        $this->withSession(['cart' => [$this->linea($producto, 5)]])->get(route('cart.index'));

        // Una segunda lectura (p. ej. el router.reload tras cambiar un cupón) ya no baja nada, pero conserva el aviso.
        $items = $this->get(route('cart.index'))->viewData('page')['props']['cartItems'];
        $this->assertSame(3, $items[0]['quantity']);
        $this->assertSame(['de' => 5, 'a' => 3], $items[0]['stock_info']['aviso']);

        // El cliente baja a 2: el aviso ya no corresponde.
        $this->patchJson(route('cart.update'), ['line_key' => $items[0]['line_key'], 'quantity' => 2])->assertOk();
        $items = $this->get(route('cart.index'))->viewData('page')['props']['cartItems'];
        $this->assertSame(2, $items[0]['quantity']);
        $this->assertNull($items[0]['stock_info']['aviso']);
    }

    public function test_una_linea_agotada_se_marca_y_conserva_su_cantidad(): void
    {
        $agotado = $this->producto(['stock' => 0]);
        $hay = $this->producto(['stock' => 10]);

        $items = collect($this->itemsDelCarrito([$this->linea($agotado, 2), $this->linea($hay, 1)]))->keyBy(fn ($i) => $i['product']['id']);

        $this->assertTrue($items[$agotado->id]['stock_info']['sin_stock']);
        $this->assertSame(0, $items[$agotado->id]['stock_info']['tope']);
        $this->assertSame(2, $items[$agotado->id]['quantity']);
        $this->assertNull($items[$agotado->id]['stock_info']['aviso']);

        $this->assertFalse($items[$hay->id]['stock_info']['sin_stock']);
    }

    public function test_un_producto_ilimitado_o_una_variante_sin_stock_no_se_limitan(): void
    {
        $ilimitado = $this->producto(['stock' => 9999]);
        $conColor = $this->producto(['stock' => 0]);
        $libre = ProductVariant::factory()->create(['product_id' => $conColor->id, 'stock' => null]);

        $items = collect($this->itemsDelCarrito([$this->linea($ilimitado, 30), $this->linea($conColor, 12, $libre)]))
            ->keyBy(fn ($i) => $i['product']['id']);

        foreach ([$ilimitado, $conColor] as $producto) {
            $this->assertNull($items[$producto->id]['stock_info']['tope']);
            $this->assertFalse($items[$producto->id]['stock_info']['sin_stock']);
            $this->assertNull($items[$producto->id]['stock_info']['aviso']);
        }
        $this->assertSame(30, $items[$ilimitado->id]['quantity']);
        $this->assertSame(12, $items[$conColor->id]['quantity']);
    }

    public function test_con_color_manda_el_stock_de_la_variante_y_no_el_del_producto(): void
    {
        $producto = $this->producto(['stock' => 99]);
        $roja = ProductVariant::factory()->create(['product_id' => $producto->id, 'name' => 'Roja', 'stock' => 2]);
        $azul = ProductVariant::factory()->create(['product_id' => $producto->id, 'name' => 'Azul', 'stock' => 20]);

        $items = collect($this->itemsDelCarrito([$this->linea($producto, 4, $roja), $this->linea($producto, 4, $azul)]))
            ->keyBy(fn ($i) => $i['variant']['name']);

        $this->assertSame(2, $items['Roja']['quantity']);
        $this->assertSame(['de' => 4, 'a' => 2], $items['Roja']['stock_info']['aviso']);
        $this->assertSame(4, $items['Azul']['quantity']);
        $this->assertNull($items['Azul']['stock_info']['aviso']);
    }

    public function test_dos_lineas_del_mismo_producto_y_color_comparten_el_disponible(): void
    {
        $producto = $this->producto(['stock' => 99]);
        $roja = ProductVariant::factory()->create(['product_id' => $producto->id, 'name' => 'Roja', 'stock' => 4]);
        $grabado = Addon::factory()->create();
        $producto->addons()->attach($grabado->id);

        // Mismo color, la segunda con una personalización: son dos líneas que salen de las mismas 4 unidades.
        $items = $this->itemsDelCarrito([$this->linea($producto, 3, $roja), $this->linea($producto, 3, $roja, [$grabado->id])]);

        $this->assertCount(2, $items);
        $this->assertSame(3, $items[0]['quantity']);
        $this->assertNull($items[0]['stock_info']['aviso']);
        $this->assertSame(1, $items[1]['quantity']);
        $this->assertSame(['de' => 3, 'a' => 1], $items[1]['stock_info']['aviso']);
        // El tope de cada una tiene en cuenta lo que consume la otra: 3 + 1 = 4 en total.
        $this->assertSame(3, $items[0]['stock_info']['tope']);
        $this->assertSame(1, $items[1]['stock_info']['tope']);
    }

    public function test_si_las_lineas_que_comparten_stock_lo_agotan_la_segunda_queda_sin_stock(): void
    {
        $producto = $this->producto(['stock' => 2]);
        $grabado = Addon::factory()->create();
        $producto->addons()->attach($grabado->id);

        $items = $this->itemsDelCarrito([$this->linea($producto, 2), $this->linea($producto, 1, null, [$grabado->id])]);

        $this->assertFalse($items[0]['stock_info']['sin_stock']);
        $this->assertTrue($items[1]['stock_info']['sin_stock']);
        $this->assertSame(1, $items[1]['quantity']);
    }

    public function test_un_producto_suelto_y_el_mismo_dentro_de_un_combo_comparten_el_disponible(): void
    {
        $producto = $this->producto(['stock' => 5]);
        $combo = Combo::create(['title' => 'Combo', 'price' => 3000, 'is_active' => true]);
        $combo->items()->create(['product_id' => $producto->id, 'quantity' => 2, 'sort_order' => 0]);

        // 3 sueltas + 2 combos (que piden 2 cada uno) = 7 unidades, hay 5: el combo se ajusta a 1 (consume 2).
        $items = $this->itemsDelCarrito([
            $this->linea($producto, 3),
            [
                'line_key' => "combo:{$combo->id}",
                'combo_id' => $combo->id,
                'quantity' => 2,
                'component_selections' => [['product_id' => $producto->id, 'variant_id' => null, 'custom_color_text' => null]],
            ],
        ]);

        $this->assertSame(3, $items[0]['quantity']);
        $this->assertSame(1, $items[1]['quantity']);
        $this->assertSame(['de' => 2, 'a' => 1], $items[1]['stock_info']['aviso']);
    }

    public function test_las_claves_numericas_del_carrito_viejo_se_resuelven_por_su_line_key(): void
    {
        // Shape viejo: {product_id: cantidad}. Las claves numéricas no pueden perderse ni renumerarse.
        $uno = $this->producto(['stock' => 4]);
        $dos = $this->producto(['stock' => 0]);

        $items = collect($this->itemsDelCarrito([$dos->id => 2, $uno->id => 9]))->keyBy(fn ($i) => $i['product']['id']);

        $this->assertTrue($items[$dos->id]['stock_info']['sin_stock']);
        $this->assertSame(4, $items[$uno->id]['quantity']);
        $this->assertSame(['de' => 9, 'a' => 4], $items[$uno->id]['stock_info']['aviso']);
    }

    public function test_el_drawer_y_el_checkout_traen_el_mismo_aviso(): void
    {
        $producto = $this->producto(['stock' => 2]);
        $agotado = $this->producto(['stock' => 0]);

        $this->withSession(['cart' => [$this->linea($producto, 6), $this->linea($agotado, 1)]])
            ->getJson(route('cart.preview'))
            ->assertOk()
            ->assertJsonPath('cartItems.0.quantity', 2)
            ->assertJsonPath('cartItems.0.stock_info.aviso', ['de' => 6, 'a' => 2])
            ->assertJsonPath('cartItems.1.stock_info.sin_stock', true);

        $this->get(route('cart.checkout'))->assertInertia(fn (Assert $page) => $page
            ->component('Cart/Checkout')
            ->where('cartItems.0.stock_info.aviso', ['de' => 6, 'a' => 2])
            ->where('cartItems.1.stock_info.sin_stock', true)
        );
    }

    public function test_agregar_no_deja_pasar_el_stock_que_comparten_otras_lineas(): void
    {
        $producto = $this->producto(['stock' => 99]);
        $roja = ProductVariant::factory()->create(['product_id' => $producto->id, 'name' => 'Roja', 'stock' => 4]);
        $grabado = Addon::factory()->create();
        $producto->addons()->attach($grabado->id);

        $this->postJson(route('cart.add'), ['product_id' => $producto->id, 'variant_id' => $roja->id, 'quantity' => 3])->assertOk();

        // La línea con grabado es otra, pero sale del mismo color: 3 + 2 = 5 > 4.
        $this->postJson(route('cart.add'), ['product_id' => $producto->id, 'variant_id' => $roja->id, 'addon_ids' => [$grabado->id], 'quantity' => 2])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->postJson(route('cart.add'), ['product_id' => $producto->id, 'variant_id' => $roja->id, 'addon_ids' => [$grabado->id], 'quantity' => 1])
            ->assertOk();
    }
}
