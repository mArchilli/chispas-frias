<?php

namespace Tests\Feature;

use App\Enums\EstadoOrden;
use App\Enums\MotivoMovimientoStock;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderStatusUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function updateStatus(Order $order, string $estado)
    {
        $admin = User::factory()->create();

        return $this->actingAs($admin)->patch(route('admin.orders.update-status', $order), [
            'estado' => $estado,
        ]);
    }

    /**
     * Crea una orden pendiente con un item de producto (stock conocido) y, opcionalmente,
     * un item adicional con product_id nulo (producto borrado).
     */
    private function crearOrdenConItem(Product $product, int $cantidad = 3, bool $conItemBorrado = false): Order
    {
        $order = Order::factory()->create(['estado' => EstadoOrden::Pendiente]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_title' => $product->title,
            'cantidad' => $cantidad,
            'precio_unitario' => 100,
            'subtotal' => 100 * $cantidad,
        ]);

        if ($conItemBorrado) {
            $order->items()->create([
                'product_id' => null,
                'product_title' => 'Producto borrado',
                'cantidad' => 2,
                'precio_unitario' => 50,
                'subtotal' => 100,
            ]);
        }

        return $order;
    }

    public function test_pendiente_puede_pasar_a_despachado(): void
    {
        $order = Order::factory()->create(['estado' => EstadoOrden::Pendiente]);

        $response = $this->updateStatus($order, EstadoOrden::Despachado->value);

        $response->assertSessionHasNoErrors();
        $this->assertSame(EstadoOrden::Despachado, $order->fresh()->estado);
    }

    public function test_despachado_puede_volver_a_pendiente(): void
    {
        $order = Order::factory()->despachado()->create();

        $response = $this->updateStatus($order, EstadoOrden::Pendiente->value);

        $response->assertSessionHasNoErrors();
        $this->assertSame(EstadoOrden::Pendiente, $order->fresh()->estado);
    }

    public function test_pendiente_puede_pasar_a_cancelado(): void
    {
        $order = Order::factory()->create(['estado' => EstadoOrden::Pendiente]);

        $response = $this->updateStatus($order, EstadoOrden::Cancelado->value);

        $response->assertSessionHasNoErrors();
        $this->assertSame(EstadoOrden::Cancelado, $order->fresh()->estado);
    }

    public function test_despachado_no_puede_pasar_directo_a_cancelado(): void
    {
        $order = Order::factory()->despachado()->create();

        $response = $this->updateStatus($order, EstadoOrden::Cancelado->value);

        $response->assertSessionHasErrors('estado');
        $this->assertSame(EstadoOrden::Despachado, $order->fresh()->estado);
    }

    // --- Reposición de stock al cancelar --------------------------------------------

    public function test_cancelar_desde_pendiente_repone_stock_y_crea_movimiento(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $order = $this->crearOrdenConItem($product, cantidad: 3);

        $response = $this->updateStatus($order, EstadoOrden::Cancelado->value);

        $response->assertSessionHasNoErrors();
        $this->assertSame(EstadoOrden::Cancelado, $order->fresh()->estado);

        $product->refresh();
        $this->assertSame(13, $product->stock);

        $movimiento = StockMovement::where('order_id', $order->id)
            ->where('product_id', $product->id)
            ->first();

        $this->assertNotNull($movimiento);
        $this->assertSame(3, $movimiento->cantidad);
        $this->assertSame(MotivoMovimientoStock::OrdenCancelada, $movimiento->motivo);
        $this->assertSame(13, $movimiento->stock_resultante);
    }

    public function test_pasar_a_despachado_no_toca_stock(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $order = $this->crearOrdenConItem($product, cantidad: 3);

        $response = $this->updateStatus($order, EstadoOrden::Despachado->value);

        $response->assertSessionHasNoErrors();
        $this->assertSame(EstadoOrden::Despachado, $order->fresh()->estado);

        $product->refresh();
        $this->assertSame(10, $product->stock);
        $this->assertSame(0, StockMovement::where('order_id', $order->id)->count());
    }

    public function test_volver_a_pendiente_desde_despachado_no_toca_stock(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $order = $this->crearOrdenConItem($product, cantidad: 3);
        $order->update(['estado' => EstadoOrden::Despachado]);

        $response = $this->updateStatus($order, EstadoOrden::Pendiente->value);

        $response->assertSessionHasNoErrors();
        $this->assertSame(EstadoOrden::Pendiente, $order->fresh()->estado);

        $product->refresh();
        $this->assertSame(10, $product->stock);
        $this->assertSame(0, StockMovement::where('order_id', $order->id)->count());
    }

    public function test_transicion_invalida_despachado_a_cancelado_no_genera_movimiento_de_stock(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $order = $this->crearOrdenConItem($product, cantidad: 3);
        $order->update(['estado' => EstadoOrden::Despachado]);

        $response = $this->updateStatus($order, EstadoOrden::Cancelado->value);

        $response->assertSessionHasErrors('estado');
        $this->assertSame(EstadoOrden::Despachado, $order->fresh()->estado);

        $product->refresh();
        $this->assertSame(10, $product->stock);
        $this->assertSame(0, StockMovement::where('order_id', $order->id)->count());
    }

    public function test_cancelar_una_orden_ya_cancelada_no_duplica_la_reposicion_de_stock(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $order = $this->crearOrdenConItem($product, cantidad: 3);
        $admin = User::factory()->create();

        $firstResponse = $this->actingAs($admin)->patch(route('admin.orders.update-status', $order), [
            'estado' => EstadoOrden::Cancelado->value,
        ]);
        $firstResponse->assertSessionHasNoErrors();
        $this->assertSame(EstadoOrden::Cancelado, $order->fresh()->estado);

        $product->refresh();
        $this->assertSame(13, $product->stock);

        $secondResponse = $this->actingAs($admin)->patch(route('admin.orders.update-status', $order), [
            'estado' => EstadoOrden::Cancelado->value,
        ]);

        $secondResponse->assertSessionHasErrors('estado');
        $this->assertSame(EstadoOrden::Cancelado, $order->fresh()->estado);

        $product->refresh();
        $this->assertSame(13, $product->stock);
        $this->assertSame(
            1,
            StockMovement::where('order_id', $order->id)
                ->where('motivo', MotivoMovimientoStock::OrdenCancelada)
                ->count()
        );
    }

    public function test_cancelar_orden_con_item_de_producto_borrado_repone_los_demas_items(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $order = $this->crearOrdenConItem($product, cantidad: 3, conItemBorrado: true);

        $response = $this->updateStatus($order, EstadoOrden::Cancelado->value);

        $response->assertSessionHasNoErrors();
        $this->assertSame(EstadoOrden::Cancelado, $order->fresh()->estado);

        $product->refresh();
        $this->assertSame(13, $product->stock);

        $this->assertSame(
            1,
            StockMovement::where('order_id', $order->id)
                ->where('motivo', MotivoMovimientoStock::OrdenCancelada)
                ->count()
        );
        $this->assertSame(0, StockMovement::where('order_id', $order->id)->whereNull('product_id')->count());
    }

    public function test_cancelado_no_puede_pasar_a_pendiente(): void
    {
        $order = Order::factory()->cancelado()->create();

        $response = $this->updateStatus($order, EstadoOrden::Pendiente->value);

        $response->assertSessionHasErrors('estado');
        $this->assertSame(EstadoOrden::Cancelado, $order->fresh()->estado);
    }

    public function test_cancelado_no_puede_pasar_a_despachado(): void
    {
        $order = Order::factory()->cancelado()->create();

        $response = $this->updateStatus($order, EstadoOrden::Despachado->value);

        $response->assertSessionHasErrors('estado');
        $this->assertSame(EstadoOrden::Cancelado, $order->fresh()->estado);
    }

    public function test_cancelar_una_orden_ya_cancelada_es_rechazado(): void
    {
        $order = Order::factory()->create(['estado' => EstadoOrden::Pendiente]);
        $admin = User::factory()->create();

        $firstResponse = $this->actingAs($admin)->patch(route('admin.orders.update-status', $order), [
            'estado' => EstadoOrden::Cancelado->value,
        ]);
        $firstResponse->assertSessionHasNoErrors();
        $this->assertSame(EstadoOrden::Cancelado, $order->fresh()->estado);

        $secondResponse = $this->actingAs($admin)->patch(route('admin.orders.update-status', $order), [
            'estado' => EstadoOrden::Cancelado->value,
        ]);

        $secondResponse->assertSessionHasErrors('estado');
        $this->assertSame(EstadoOrden::Cancelado, $order->fresh()->estado);
    }

    public function test_un_usuario_no_autenticado_no_puede_cambiar_el_estado(): void
    {
        $order = Order::factory()->create(['estado' => EstadoOrden::Pendiente]);

        $response = $this->patch(route('admin.orders.update-status', $order), [
            'estado' => EstadoOrden::Despachado->value,
        ]);

        $response->assertRedirect(route('login'));
        $this->assertSame(EstadoOrden::Pendiente, $order->fresh()->estado);
    }

    public function test_rechaza_un_estado_que_no_existe_en_el_enum(): void
    {
        $order = Order::factory()->create(['estado' => EstadoOrden::Pendiente]);

        $response = $this->updateStatus($order, 'en-camino');

        $response->assertSessionHasErrors('estado');
        $this->assertSame(EstadoOrden::Pendiente, $order->fresh()->estado);
    }
}
