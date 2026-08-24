<?php

namespace Tests\Feature;

use App\Enums\EstadoOrden;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reposición del uso de un código de descuento al cancelar una orden desde
 * el admin, en el mismo espíritu que AdminOrderStatusUpdateTest para la
 * reposición de stock: cancelar pendiente -> cancelado repone, y cancelar dos
 * veces no debe reponer dos veces (la máquina de estados de EstadoOrden ya
 * bloquea la segunda cancelación, pero este test confirma que, si la
 * bloqueara, el flag `discount_usage_repuesto` igual lo evitaría).
 */
class AdminOrderCancelDiscountCodeTest extends TestCase
{
    use RefreshDatabase;

    private function crearCodigo(array $overrides = []): DiscountCode
    {
        return DiscountCode::create(array_merge([
            'code' => 'CODE' . random_int(100000, 999999),
            'description' => null,
            'percentage' => 10,
            'min_purchase_amount' => null,
            'usage_limit' => null,
            'usage_count' => 0,
            'start_date' => null,
            'end_date' => null,
            'is_active' => true,
        ], $overrides));
    }

    private function crearOrdenConCodigo(DiscountCode $discountCode, Product $product, EstadoOrden $estado = EstadoOrden::Pendiente): Order
    {
        $order = Order::factory()->create([
            'estado' => $estado,
            'discount_code_id' => $discountCode->id,
            'discount_code' => $discountCode->code,
            'subtotal' => 1000,
            'discount_amount' => 100,
            'total' => 900,
            'discount_usage_repuesto' => false,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_title' => $product->title,
            'cantidad' => 2,
            'precio_unitario' => 500,
            'subtotal' => 1000,
        ]);

        return $order;
    }

    private function updateStatus(Order $order, string $estado)
    {
        $admin = User::factory()->create();

        return $this->actingAs($admin)->patch(route('admin.orders.update-status', $order), [
            'estado' => $estado,
        ]);
    }

    public function test_cancelar_desde_pendiente_repone_el_uso_del_codigo_de_descuento(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $discountCode = $this->crearCodigo(['usage_count' => 3]);
        $order = $this->crearOrdenConCodigo($discountCode, $product);

        $response = $this->updateStatus($order, EstadoOrden::Cancelado->value);

        $response->assertSessionHasNoErrors();
        $this->assertSame(EstadoOrden::Cancelado, $order->fresh()->estado);

        $discountCode->refresh();
        $this->assertSame(2, $discountCode->usage_count);

        $order->refresh();
        $this->assertTrue($order->discount_usage_repuesto);
    }

    public function test_cancelar_una_orden_ya_cancelada_no_duplica_la_reposicion_del_codigo(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $discountCode = $this->crearCodigo(['usage_count' => 3]);
        $order = $this->crearOrdenConCodigo($discountCode, $product);
        $admin = User::factory()->create();

        $firstResponse = $this->actingAs($admin)->patch(route('admin.orders.update-status', $order), [
            'estado' => EstadoOrden::Cancelado->value,
        ]);
        $firstResponse->assertSessionHasNoErrors();

        $discountCode->refresh();
        $this->assertSame(2, $discountCode->usage_count);

        // EstadoOrden::Cancelado no puede transicionar a ningún otro estado, así que
        // este segundo intento se rechaza en la validación de la máquina de estados,
        // antes de siquiera llegar a la lógica de reposición.
        $secondResponse = $this->actingAs($admin)->patch(route('admin.orders.update-status', $order), [
            'estado' => EstadoOrden::Cancelado->value,
        ]);
        $secondResponse->assertSessionHasErrors('estado');

        $discountCode->refresh();
        $this->assertSame(2, $discountCode->usage_count);
        $this->assertTrue($order->fresh()->discount_usage_repuesto);
    }

    public function test_pasar_a_despachado_no_repone_el_codigo_de_descuento(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $discountCode = $this->crearCodigo(['usage_count' => 3]);
        $order = $this->crearOrdenConCodigo($discountCode, $product);

        $response = $this->updateStatus($order, EstadoOrden::Despachado->value);

        $response->assertSessionHasNoErrors();

        $discountCode->refresh();
        $this->assertSame(3, $discountCode->usage_count);
        $this->assertFalse($order->fresh()->discount_usage_repuesto);
    }

    public function test_cancelar_una_orden_sin_codigo_de_descuento_no_falla(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $order = Order::factory()->create(['estado' => EstadoOrden::Pendiente]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_title' => $product->title,
            'cantidad' => 1,
            'precio_unitario' => 100,
            'subtotal' => 100,
        ]);

        $response = $this->updateStatus($order, EstadoOrden::Cancelado->value);

        $response->assertSessionHasNoErrors();
        $this->assertSame(EstadoOrden::Cancelado, $order->fresh()->estado);
    }

    public function test_reponer_el_uso_del_codigo_no_baja_de_cero_si_ya_estaba_en_cero(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $discountCode = $this->crearCodigo(['usage_count' => 0]);
        $order = $this->crearOrdenConCodigo($discountCode, $product);

        $response = $this->updateStatus($order, EstadoOrden::Cancelado->value);

        $response->assertSessionHasNoErrors();

        $discountCode->refresh();
        $this->assertSame(0, $discountCode->usage_count);
    }
}
