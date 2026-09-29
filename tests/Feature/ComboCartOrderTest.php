<?php

namespace Tests\Feature;

use App\Enums\MotivoMovimientoStock;
use App\Models\Category;
use App\Models\Combo;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComboCartOrderTest extends TestCase
{
    use RefreshDatabase;

    private function validCustomerData(array $overrides = []): array
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

    /**
     * Combo con un producto sin variantes (qty 1) y otro con variante de color
     * (qty 2). Precio fijo 5000, envío gratis.
     *
     * @return array{combo: Combo, productA: Product, productB: Product, rojo: ProductVariant}
     */
    private function crearCombo(bool $freeShipping = true): array
    {
        $category = Category::factory()->create();

        $productA = Product::factory()->for($category)->create(['title' => 'Bengala', 'price' => 1000, 'stock' => 10]);
        $productB = Product::factory()->for($category)->create(['title' => 'Volcán', 'price' => 500, 'stock' => 99]);
        $rojo = ProductVariant::factory()->create(['product_id' => $productB->id, 'name' => 'Rojo', 'price_addon' => 200, 'stock' => 8]);

        $combo = Combo::create([
            'title' => 'Combo Fiesta',
            'description' => 'Para tu evento',
            'price' => 5000,
            'is_free_shipping' => $freeShipping,
            'is_active' => true,
        ]);
        $combo->items()->create(['product_id' => $productA->id, 'quantity' => 1, 'sort_order' => 0]);
        $combo->items()->create(['product_id' => $productB->id, 'quantity' => 2, 'sort_order' => 1]);

        return compact('combo', 'productA', 'productB', 'rojo');
    }

    private function comboCartLine(array $ctx, int $quantity = 1): array
    {
        return [[
            'combo_id' => $ctx['combo']->id,
            'quantity' => $quantity,
            'component_selections' => [
                ['product_id' => $ctx['productA']->id, 'variant_id' => null, 'custom_color_text' => null],
                ['product_id' => $ctx['productB']->id, 'variant_id' => $ctx['rojo']->id, 'custom_color_text' => null],
            ],
        ]];
    }

    public function test_it_adds_a_combo_to_the_cart_choosing_the_color_of_each_component(): void
    {
        $ctx = $this->crearCombo();

        $response = $this->postJson(route('cart.combo.add'), [
            'combo_id' => $ctx['combo']->id,
            'quantity' => 1,
            'selections' => [
                ['product_id' => $ctx['productB']->id, 'variant_id' => $ctx['rojo']->id],
            ],
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $cart = session('cart');
        $this->assertCount(1, $cart);
        $this->assertSame($ctx['combo']->id, $cart[0]['combo_id']);
        $this->assertSame(1, $cart[0]['quantity']);
        $this->assertCount(2, $cart[0]['component_selections']);
    }

    public function test_it_rejects_adding_a_combo_when_a_component_color_is_missing(): void
    {
        $ctx = $this->crearCombo();

        $response = $this->postJson(route('cart.combo.add'), [
            'combo_id' => $ctx['combo']->id,
            'quantity' => 1,
            'selections' => [], // falta el color del producto con variantes
        ]);

        $response->assertStatus(422)->assertJson(['success' => false]);
        $this->assertNull(session('cart'));
    }

    public function test_it_creates_an_order_with_a_combo_line_and_snapshots_the_components(): void
    {
        $ctx = $this->crearCombo();

        $response = $this->withSession(['cart' => $this->comboCartLine($ctx)])
            ->postJson(route('cart.whatsapp'), ['customer_data' => $this->validCustomerData()]);

        $response->assertOk()->assertJson([
            'success' => true,
            'total' => 5000.0,
            'itemCount' => 1,
        ]);

        $order = Order::first();
        $this->assertTrue((bool) $order->free_shipping);
        $this->assertEquals(5000.0, (float) $order->total);
        $this->assertStringContainsString('Combo Fiesta', $order->mensaje_whatsapp);
        $this->assertStringContainsString('Envío gratis', $order->mensaje_whatsapp);

        $this->assertSame(1, $order->items()->count());
        $item = $order->items()->first();
        $this->assertNull($item->product_id);
        $this->assertSame($ctx['combo']->id, $item->combo_id);
        $this->assertSame('Combo Fiesta', $item->product_title);
        $this->assertEquals(5000.0, (float) $item->precio_unitario);
        $this->assertEquals(5000.0, (float) $item->subtotal);
        $this->assertCount(2, $item->combo_selections);
        $this->assertSame('Rojo', $item->combo_selections[1]['variant_name']);

        $this->assertNull(session('cart'));
    }

    public function test_it_discounts_component_and_variant_stock_for_the_combo(): void
    {
        $ctx = $this->crearCombo();

        // 2 combos: productA -> 2, variante Rojo -> 4 (2 por combo × 2 combos).
        $this->withSession(['cart' => $this->comboCartLine($ctx, quantity: 2)])
            ->postJson(route('cart.whatsapp'), ['customer_data' => $this->validCustomerData()])
            ->assertOk()
            ->assertJson(['success' => true]);

        $order = Order::first();

        $this->assertSame(8, $ctx['productA']->fresh()->stock); // 10 - 2
        $this->assertSame(4, $ctx['rojo']->fresh()->stock);     // 8 - 4

        $movA = StockMovement::where('product_id', $ctx['productA']->id)->where('order_id', $order->id)->first();
        $this->assertNotNull($movA);
        $this->assertSame(-2, $movA->cantidad);
        $this->assertSame(MotivoMovimientoStock::OrdenCreada, $movA->motivo);

        $movRojo = StockMovement::where('product_variant_id', $ctx['rojo']->id)->where('order_id', $order->id)->first();
        $this->assertNotNull($movRojo);
        $this->assertSame(-4, $movRojo->cantidad);
    }

    public function test_it_rejects_the_combo_order_when_a_component_lacks_stock(): void
    {
        $ctx = $this->crearCombo();
        // La variante Rojo tiene stock 8; 5 combos necesitan 10 → falta stock.
        $this->withSession(['cart' => $this->comboCartLine($ctx, quantity: 5)])
            ->postJson(route('cart.whatsapp'), ['customer_data' => $this->validCustomerData()])
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertSame(0, Order::count());
        $this->assertSame(10, $ctx['productA']->fresh()->stock);
        $this->assertSame(8, $ctx['rojo']->fresh()->stock);
        $this->assertSame(0, StockMovement::count());
    }

    public function test_a_combo_without_free_shipping_does_not_mark_the_order(): void
    {
        $ctx = $this->crearCombo(freeShipping: false);

        $this->withSession(['cart' => $this->comboCartLine($ctx)])
            ->postJson(route('cart.whatsapp'), ['customer_data' => $this->validCustomerData()])
            ->assertOk();

        $this->assertFalse((bool) Order::first()->free_shipping);
    }
}
