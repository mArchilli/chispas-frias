<?php

namespace Tests\Feature;

use App\Enums\MotivoMovimientoStock;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CartWhatsAppOrderTest extends TestCase
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
            'address' => 'Calle Falsa',
            'number' => '123',
            'between_streets' => 'San Martín y Belgrano',
            'postal_code' => '1900',
            'phone' => '1122334455',
            'email' => 'juana@example.com',
            'observations' => 'Tocar timbre.',
        ], $overrides);
    }

    public function test_it_creates_an_order_with_items_from_a_real_cart_and_keeps_response_shape(): void
    {
        $category = Category::factory()->create();
        $productA = Product::factory()->for($category)->create(['title' => 'Chispita A', 'price' => 1000, 'stock' => 50]);
        $productB = Product::factory()->for($category)->create(['title' => 'Chispita B', 'price' => 500, 'stock' => 50]);

        $response = $this->withSession([
            'cart' => [
                $productA->id => 2,
                $productB->id => 3,
            ],
        ])->postJson(route('cart.whatsapp'), [
            'customer_data' => $this->validCustomerData(),
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['success', 'message', 'total', 'itemCount', 'order_id']);
        $response->assertJson([
            'success' => true,
            'total' => 3500.0,
            'itemCount' => 2,
        ]);

        $this->assertSame(1, Order::count());
        $order = Order::first();

        $this->assertSame($order->id, $response->json('order_id'));

        $this->assertSame('Juana', $order->name);
        $this->assertSame('Pérez', $order->lastname);
        $this->assertSame('30111222', $order->dni);
        $this->assertSame('1900', $order->postal_code);
        $this->assertEquals(3500.0, (float) $order->total);
        $this->assertNotEmpty($order->mensaje_whatsapp);
        $this->assertStringContainsString('Juana Pérez', $order->mensaje_whatsapp);
        $this->assertNull($order->user_id);

        $this->assertSame(2, $order->items()->count());

        $itemA = $order->items()->where('product_id', $productA->id)->first();
        $this->assertSame(2, $itemA->cantidad);
        $this->assertEquals(1000.0, (float) $itemA->precio_unitario);
        $this->assertEquals(2000.0, (float) $itemA->subtotal);
        $this->assertSame('Chispita A', $itemA->product_title);

        $itemB = $order->items()->where('product_id', $productB->id)->first();
        $this->assertSame(3, $itemB->cantidad);
        $this->assertEquals(500.0, (float) $itemB->precio_unitario);
        $this->assertEquals(1500.0, (float) $itemB->subtotal);

        $this->assertNull(session('cart'));
    }

    public function test_it_discounts_stock_and_creates_stock_movements_when_the_order_is_created(): void
    {
        $category = Category::factory()->create();
        $productA = Product::factory()->for($category)->create(['title' => 'Chispita A', 'price' => 1000, 'stock' => 10]);
        $productB = Product::factory()->for($category)->create(['title' => 'Chispita B', 'price' => 500, 'stock' => 5]);

        $response = $this->withSession([
            'cart' => [
                $productA->id => 2,
                $productB->id => 3,
            ],
        ])->postJson(route('cart.whatsapp'), [
            'customer_data' => $this->validCustomerData(),
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $order = Order::first();

        $productA->refresh();
        $productB->refresh();

        $this->assertSame(8, $productA->stock);
        $this->assertSame(2, $productB->stock);

        $movimientoA = StockMovement::where('product_id', $productA->id)->where('order_id', $order->id)->first();
        $movimientoB = StockMovement::where('product_id', $productB->id)->where('order_id', $order->id)->first();

        $this->assertNotNull($movimientoA);
        $this->assertSame(-2, $movimientoA->cantidad);
        $this->assertSame(MotivoMovimientoStock::OrdenCreada, $movimientoA->motivo);
        $this->assertSame(8, $movimientoA->stock_resultante);

        $this->assertNotNull($movimientoB);
        $this->assertSame(-3, $movimientoB->cantidad);
        $this->assertSame(MotivoMovimientoStock::OrdenCreada, $movimientoB->motivo);
        $this->assertSame(2, $movimientoB->stock_resultante);

        $this->assertNull(session('cart'));
    }

    public function test_it_rejects_the_order_when_an_item_lacks_stock_and_leaves_everything_untouched(): void
    {
        $category = Category::factory()->create();
        $productOk = Product::factory()->for($category)->create(['title' => 'Con stock', 'price' => 1000, 'stock' => 10]);
        $productSinStock = Product::factory()->for($category)->create(['title' => 'Sin stock', 'price' => 500, 'stock' => 2]);

        $response = $this->withSession([
            'cart' => [
                $productOk->id => 2,
                $productSinStock->id => 5,
            ],
        ])->postJson(route('cart.whatsapp'), [
            'customer_data' => $this->validCustomerData(),
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
        $response->assertJsonPath('stock_insuficiente.0.product_id', $productSinStock->id);
        $response->assertJsonPath('stock_insuficiente.0.product_title', 'Sin stock');
        $response->assertJsonPath('stock_insuficiente.0.cantidad_solicitada', 5);
        $response->assertJsonPath('stock_insuficiente.0.stock_disponible', 2);

        $productOk->refresh();
        $productSinStock->refresh();

        $this->assertSame(10, $productOk->stock);
        $this->assertSame(2, $productSinStock->stock);

        $this->assertSame(0, Order::count());
        $this->assertSame(0, OrderItem::count());
        $this->assertSame(0, StockMovement::count());

        $this->assertSame([
            $productOk->id => 2,
            $productSinStock->id => 5,
        ], session('cart'));
    }

    public function test_it_rejects_the_order_on_a_stock_race_condition_between_the_optimistic_check_and_the_real_discount(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create(['title' => 'Carrera', 'price' => 1000, 'stock' => 2]);

        // Simula una condición de carrera real: justo después de que el chequeo
        // optimista de validarDisponibilidad() lee stock=2 (suficiente), otra
        // "conexión" concurrente vende las 2 unidades restantes, antes de que
        // descontar() tome el lock definitivo dentro de la transacción.
        $queryCount = 0;
        DB::listen(function ($query) use ($product, &$queryCount) {
            if (! str_contains($query->sql, 'from "products"') || ! str_contains($query->sql, '"id" = ?')) {
                return;
            }

            if (($query->bindings[0] ?? null) !== $product->id) {
                return;
            }

            $queryCount++;

            // 1ra lectura: getCartItems(). 2da lectura: el chequeo optimista.
            if ($queryCount === 2) {
                DB::table('products')->where('id', $product->id)->update(['stock' => 0]);
            }
        });

        $response = $this->withSession([
            'cart' => [$product->id => 2],
        ])->postJson(route('cart.whatsapp'), [
            'customer_data' => $this->validCustomerData(),
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
        $response->assertJsonPath('stock_insuficiente.0.product_id', $product->id);
        $response->assertJsonPath('stock_insuficiente.0.cantidad_solicitada', 2);
        $response->assertJsonPath('stock_insuficiente.0.stock_disponible', 0);

        $this->assertSame(0, Order::count());
        $this->assertSame(0, OrderItem::count());
        $this->assertSame(0, StockMovement::count());

        $product->refresh();
        $this->assertSame(0, $product->stock);

        $this->assertSame([$product->id => 2], session('cart'));
    }

    public function test_it_associates_the_order_with_the_authenticated_user(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create(['price' => 200, 'stock' => 50]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->withSession([
            'cart' => [$product->id => 1],
        ])->postJson(route('cart.whatsapp'), [
            'customer_data' => $this->validCustomerData(),
        ]);

        $response->assertOk();

        $order = Order::first();
        $this->assertSame($user->id, $order->user_id);
    }

    public function test_it_does_not_create_an_order_when_the_cart_is_empty(): void
    {
        $response = $this->withSession(['cart' => []])
            ->postJson(route('cart.whatsapp'), [
                'customer_data' => $this->validCustomerData(),
            ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'El carrito está vacío.',
        ]);

        $this->assertSame(0, Order::count());
        $this->assertSame(0, OrderItem::count());
    }

    public function test_it_rejects_missing_customer_data_without_creating_an_order_or_touching_the_cart(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create(['price' => 300, 'stock' => 50]);

        $response = $this->withSession([
            'cart' => [$product->id => 1],
        ])->postJson(route('cart.whatsapp'), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['customer_data']);

        $this->assertSame(0, Order::count());
        $this->assertSame([$product->id => 1], session('cart'));
    }

    public function test_it_rejects_incomplete_customer_data_without_creating_an_order_or_touching_the_cart(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create(['price' => 300, 'stock' => 50]);

        $response = $this->withSession([
            'cart' => [$product->id => 1],
        ])->postJson(route('cart.whatsapp'), [
            'customer_data' => $this->validCustomerData(['name' => '', 'email' => 'no-es-un-email']),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['customer_data.name', 'customer_data.email']);

        $this->assertSame(0, Order::count());
        $this->assertSame([$product->id => 1], session('cart'));
    }

    public function test_it_excludes_a_deleted_product_from_the_order_without_erroring(): void
    {
        $category = Category::factory()->create();
        $existingProduct = Product::factory()->for($category)->create(['title' => 'Sigue viva', 'price' => 400, 'stock' => 50]);

        $deletedProduct = Product::factory()->for($category)->create(['price' => 999, 'stock' => 50]);
        $deletedProductId = $deletedProduct->id;
        $deletedProduct->delete();

        $response = $this->withSession([
            'cart' => [
                $existingProduct->id => 2,
                $deletedProductId => 5,
            ],
        ])->postJson(route('cart.whatsapp'), [
            'customer_data' => $this->validCustomerData(),
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'total' => 800.0,
            'itemCount' => 1,
        ]);

        $this->assertSame(1, Order::count());
        $order = Order::first();

        $this->assertSame($order->id, $response->json('order_id'));
        $this->assertSame(1, $order->items()->count());
        $this->assertEquals(800.0, (float) $order->total);

        $item = $order->items()->first();
        $this->assertSame($existingProduct->id, $item->product_id);
        $this->assertSame(2, $item->cantidad);

        $existingProduct->refresh();
        $this->assertSame(48, $existingProduct->stock);

        $this->assertSame(1, StockMovement::where('order_id', $order->id)->count());
        $movimiento = StockMovement::where('order_id', $order->id)->first();
        $this->assertSame($existingProduct->id, $movimiento->product_id);
        $this->assertSame(-2, $movimiento->cantidad);
        $this->assertSame(MotivoMovimientoStock::OrdenCreada, $movimiento->motivo);

        $this->assertNull(session('cart'));
    }
}
