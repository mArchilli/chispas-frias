<?php

namespace Tests\Feature\Rukoz;

use App\Enums\EstadoOrden;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\PedidosConCentral;
use Tests\TestCase;

/**
 * 10.4 — Después de confirmar, el pedido es de SOLO LECTURA en la web: no se cancela ni cambia de estado (UI y backend), ni siquiera
 * pasando por la ruta a mano. Para anular: «Para anular, hacelo en el Sistema Central». La cancelación automática no lo toca. Se
 * puede ver todo.
 */
class PedidoConfirmadoSoloLecturaTest extends TestCase
{
    use PedidosConCentral;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararCentral();
    }

    protected function tearDown(): void
    {
        $this->exigirContratoSinErrores();

        parent::tearDown();
    }

    public function test_no_se_puede_cancelar_ni_cambiar_el_estado_desde_la_web(): void
    {
        $pedido = $this->confirmado();
        $admin = $this->admin();
        $llamadasAntes = count($this->central->llamadas);

        foreach (['cancelado', 'pendiente', 'despachado'] as $destino) {
            $this->cambiarEstado($pedido, $destino, $admin)->assertSessionHasErrors('estado');
            $this->assertSame('Para anular, hacelo en el Sistema Central.', session('errors')->first('estado'));
        }

        // Tampoco un valor que la web no asigna a mano.
        foreach (['confirmado', 'empaquetado', 'entregado', 'anulado'] as $destino) {
            $this->cambiarEstado($pedido, $destino, $admin)->assertSessionHasErrors('estado');
        }

        $pedido->refresh();
        $this->assertSame(EstadoOrden::Confirmado, $pedido->estado);
        $this->assertNull($pedido->motivo_cancelacion);
        $this->assertCount($llamadasAntes, $this->central->llamadas, 'No se llamó al Central');
        $this->assertSame([], $this->central->llamadasA('POST', '#/cancelar$#'), 'No se llamó a cancelar en el Central');
        $this->assertNotSame('cancelado', $this->central->pedidos[$pedido->rukoz_ref]['estado']);
    }

    public function test_un_vendedor_tampoco_puede_cancelar_una_venta_confirmada(): void
    {
        $pedido = $this->confirmado();

        $this->cambiarEstado($pedido, 'cancelado', $this->vendedor())->assertSessionHasErrors('estado');

        $this->assertSame(EstadoOrden::Confirmado, $pedido->fresh()->estado);
    }

    public function test_despachado_empaquetado_y_entregado_en_el_central_tambien_son_de_solo_lectura(): void
    {
        $pedido = $this->confirmado();
        $ref = $pedido->rukoz_ref;
        $admin = $this->admin();

        foreach (['empaquetado' => 'empaquetado_at', 'despachado' => 'despachado_at', 'retirado' => 'retirado_at'] as $estado => $fecha) {
            $datos = $this->central->avanzar($ref, $estado, [$fecha => now()->toIso8601String()]);
            $this->assertNotNull(app(\App\Rukoz\EstadoDesdeCentral::class)->aplicar($ref, $datos['version'], $estado, $datos));

            foreach (['cancelado', 'pendiente', 'despachado'] as $destino) {
                $this->cambiarEstado($pedido, $destino, $admin)->assertSessionHasErrors('estado');
            }
            $this->assertSame(EstadoOrden::desdeCentral($estado), $pedido->fresh()->estado);
            foreach (EstadoOrden::cases() as $posible) {
                $this->assertFalse($pedido->fresh()->puedeTransicionarA($posible), "No debería poder pasar a {$posible->value}");
            }
        }

        $this->assertSame([], $this->central->llamadasA('POST', '#/cancelar$#'));
    }

    public function test_un_pedido_anulado_en_el_central_tampoco_se_reabre_desde_la_web(): void
    {
        $pedido = $this->confirmado();
        $datos = $this->central->avanzar($pedido->rukoz_ref, 'anulado', ['anulado_at' => now()->toIso8601String(), 'motivo' => 'Error']);
        app(\App\Rukoz\EstadoDesdeCentral::class)->aplicar($pedido->rukoz_ref, $datos['version'], 'anulado', $datos);

        foreach (['pendiente', 'despachado', 'cancelado'] as $destino) {
            $this->cambiarEstado($pedido, $destino)->assertSessionHasErrors('estado');
        }

        $this->assertSame(EstadoOrden::Anulado, $pedido->fresh()->estado);
    }

    public function test_la_cancelacion_automatica_no_toca_una_venta_confirmada(): void
    {
        $pedido = $this->confirmado();
        // Una venta vieja de 10 días: una reserva así se cancelaría; una venta confirmada no.
        Order::whereKey($pedido->id)->update(['created_at' => now()->subDays(10)]);

        $this->assertSame(0, Artisan::call('chispasfrias:cancelar-reservas-viejas'));

        $this->assertSame(EstadoOrden::Confirmado, $pedido->fresh()->estado);
        $this->assertSame([], $this->central->llamadasA('POST', '#/cancelar$#'));
    }

    public function test_la_cancelacion_automatica_tampoco_toca_una_venta_con_estado_pendiente_pero_confirmada(): void
    {
        // Defensa extra: aunque el estado local se hubiera quedado en pendiente, `confirmado_at` la protege.
        $pedido = $this->confirmado();
        Order::whereKey($pedido->id)->update(['estado' => EstadoOrden::Pendiente->value, 'rukoz_estado' => 'reservado', 'created_at' => now()->subDays(10)]);

        Artisan::call('chispasfrias:cancelar-reservas-viejas');

        $this->assertSame([], $this->central->llamadasA('POST', '#/cancelar$#'));
    }

    public function test_la_cancelacion_automatica_sigue_cancelando_las_reservas_sin_confirmar(): void
    {
        $reserva = $this->comprar();
        Order::whereKey($reserva->id)->update(['created_at' => now()->subDays(10)]);
        $confirmada = $this->confirmado();
        Order::whereKey($confirmada->id)->update(['created_at' => now()->subDays(10)]);

        Artisan::call('chispasfrias:cancelar-reservas-viejas');

        $this->assertSame(EstadoOrden::Cancelado, $reserva->fresh()->estado);
        $this->assertSame(EstadoOrden::Confirmado, $confirmada->fresh()->estado);
    }

    public function test_confirmar_de_nuevo_un_pedido_confirmado_no_hace_nada(): void
    {
        $pedido = $this->confirmado();
        $posts = count($this->posts());

        $this->confirmar($pedido)->assertSessionHasNoErrors();

        $this->assertCount($posts, $this->posts());
    }

    public function test_la_ficha_muestra_todo_y_no_ofrece_acciones(): void
    {
        $pedido = $this->confirmado();

        $this->actingAs($this->admin())
            ->get(route('admin.orders.show', $pedido))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Orders/Show')
                ->where('order.name', 'Cliente')
                ->where('order.observations', 'Nota original')
                ->where('order.venta.numero', $pedido->rukoz_numero_venta)
                ->where('order.estado', 'confirmado')
                ->where('order.esta_confirmado', true)
                ->where('order.puede_confirmar', false)
                ->where('order.transiciones_disponibles', [])
                ->has('order.items', 1)
            );
    }

    public function test_la_ficha_de_una_reserva_sin_confirmar_ofrece_confirmar_y_cancelar_pero_no_despachar(): void
    {
        $pedido = $this->comprar();

        $this->actingAs($this->admin())
            ->get(route('admin.orders.show', $pedido))
            ->assertInertia(fn ($page) => $page
                ->where('order.puede_confirmar', true)
                ->where('order.esta_confirmado', false)
                ->where('order.venta', null)
                ->where('order.transiciones_disponibles', ['cancelado'])
            );
    }

    public function test_un_pedido_viejo_sin_reserva_sigue_despachable_como_siempre(): void
    {
        $pedido = $this->comprarSinCentral();

        $this->actingAs($this->admin())
            ->get(route('admin.orders.show', $pedido))
            ->assertInertia(fn ($page) => $page
                ->where('order.puede_confirmar', false)
                ->where('order.esta_confirmado', false)
                ->where('order.transiciones_disponibles', ['despachado', 'cancelado'])
            );
    }

    public function test_el_listado_filtra_por_los_estados_nuevos(): void
    {
        $pedido = $this->confirmado();

        $this->actingAs($this->admin())->get(route('admin.orders.index', ['estado' => 'confirmado']))
            ->assertInertia(fn ($page) => $page->has('orders.data', 1)->where('orders.data.0.id', $pedido->id)->where('filters.estado', 'confirmado'));
        $this->actingAs($this->admin())->get(route('admin.orders.index', ['estado' => 'pendiente']))
            ->assertInertia(fn ($page) => $page->has('orders.data', 0));
    }
}
