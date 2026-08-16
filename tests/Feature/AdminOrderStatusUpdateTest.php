<?php

namespace Tests\Feature;

use App\Enums\EstadoOrden;
use App\Models\Order;
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
