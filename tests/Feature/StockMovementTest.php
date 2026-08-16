<?php

namespace Tests\Feature;

use App\Enums\MotivoMovimientoStock;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockMovementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_stock_movement_can_be_created_with_relations_and_enum_cast(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $order = Order::factory()->create();

        $movement = StockMovement::factory()->create([
            'product_id' => $product->id,
            'order_id' => $order->id,
            'cantidad' => -2,
            'motivo' => MotivoMovimientoStock::OrdenCreada,
            'stock_resultante' => 8,
        ]);

        $this->assertInstanceOf(MotivoMovimientoStock::class, $movement->motivo);
        $this->assertSame(MotivoMovimientoStock::OrdenCreada, $movement->motivo);

        $this->assertTrue($movement->product->is($product));
        $this->assertTrue($movement->order->is($order));
        $this->assertTrue($product->stockMovements->contains($movement));
    }

    public function test_stock_movement_factory_can_create_standalone_movements_without_order(): void
    {
        $movement = StockMovement::factory()->create();

        $this->assertInstanceOf(Product::class, $movement->product);
        $this->assertNull($movement->order_id);
        $this->assertNull($movement->order);
    }

    public function test_tiene_stock_disponible_compares_against_requested_quantity(): void
    {
        $product = Product::factory()->create(['stock' => 5]);

        $this->assertTrue($product->tieneStockDisponible(3));
        $this->assertTrue($product->tieneStockDisponible(5));
        $this->assertFalse($product->tieneStockDisponible(10));
    }
}
