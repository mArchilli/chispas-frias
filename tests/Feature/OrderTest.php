<?php

namespace Tests\Feature;

use App\Enums\EstadoOrden;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_order_can_be_created_with_items_and_relations_work(): void
    {
        $product = Product::factory()->create();

        $order = Order::factory()->create();

        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_title' => $product->title,
            'cantidad' => 2,
            'precio_unitario' => 100,
            'subtotal' => 200,
        ]);

        $this->assertInstanceOf(EstadoOrden::class, $order->estado);
        $this->assertSame(EstadoOrden::Pendiente, $order->estado);

        $this->assertTrue($order->items->contains($item));
        $this->assertSame(1, $order->items()->count());

        $this->assertTrue($item->order->is($order));
        $this->assertTrue($item->product->is($product));
    }

    public function test_order_item_factory_can_create_standalone_items(): void
    {
        $item = OrderItem::factory()->create();

        $this->assertInstanceOf(Order::class, $item->order);
    }
}
