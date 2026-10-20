<?php

namespace Tests\Feature\Rukoz;

use App\Enums\EstadoOrden;
use App\Models\Category;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Rukoz\EspejoStock;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Rukoz\CoreClient\Compensaciones\Compensacion;
use Rukoz\CoreClient\Eventos\StockActualizado;
use Tests\Support\CentralDePrueba;
use Tests\TestCase;

/**
 * 10.3 — Qué hace el admin con un pedido que tiene su stock reservado en el Central (`rukoz_ref`) y qué sigue haciendo con
 * los pedidos de siempre (sin `rukoz_ref`, stock local) aunque el interruptor esté encendido.
 *
 * Hoy el admin solo LISTA, MIRA y CAMBIA EL ESTADO de un pedido: no hay edición de ítems ni de cantidades (ver
 * `test_el_admin_no_tiene_edicion_de_items`), así que no hay `editarReserva`.
 */
class PedidoConReservaAdminTest extends TestCase
{
    use RefreshDatabase;

    private CentralDePrueba $central;

    protected function setUp(): void
    {
        parent::setUp();

        $this->central = CentralDePrueba::instalar();
        Event::listen(StockActualizado::class, [EspejoStock::class, 'alActualizarse']);
    }

    protected function tearDown(): void
    {
        $this->assertSame([], $this->central->erroresDeContrato);

        parent::tearDown();
    }

    private function producto(int $stock = 20): Product
    {
        return Product::factory()->for(Category::factory())->create(['title' => 'Chispa', 'price' => 1000, 'stock' => $stock, 'is_active' => true]);
    }

    /** Un pedido hecho por el checkout real: reservó en el Central y no tocó el stock local. */
    private function pedidoConReserva(Product $producto, array $sesionExtra = []): Order
    {
        $respuesta = $this->withSession(array_merge(['cart' => [['product_id' => $producto->id, 'quantity' => 3]]], $sesionExtra))
            ->postJson(route('cart.whatsapp'), ['customer_data' => [
                'name' => 'Juana', 'lastname' => 'Pérez', 'dni' => '30111222', 'province' => 'buenos-aires', 'city' => 'La Plata',
                'postal_code' => '1900', 'phone' => '1122334455', 'email' => 'juana@example.com',
            ]])->assertOk();

        return Order::findOrFail($respuesta->json('order_id'));
    }

    /** Un pedido de los de siempre: sin `rukoz_ref`, descontó stock local al crearse. */
    private function pedidoViejo(Product $producto): Order
    {
        config(['integracion.checkout' => false]);

        $respuesta = $this->withSession(['cart' => [['product_id' => $producto->id, 'quantity' => 3]]])
            ->postJson(route('cart.whatsapp'), ['customer_data' => [
                'name' => 'Viejo', 'lastname' => 'Cliente', 'dni' => '30111222', 'province' => 'buenos-aires', 'city' => 'La Plata',
                'postal_code' => '1900', 'phone' => '1122334455', 'email' => 'viejo@example.com',
            ]])->assertOk();

        config(['integracion.checkout' => true]); // el interruptor se enciende DESPUÉS: el pedido ya existía

        return Order::findOrFail($respuesta->json('order_id'));
    }

    private function cambiarEstado(Order $pedido, string $estado)
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.orders.update-status', $pedido), ['estado' => $estado]);
    }

    private function codigo(): DiscountCode
    {
        return DiscountCode::create([
            'code' => 'DIEZ', 'percentage' => 10, 'min_purchase_amount' => null, 'usage_limit' => 5, 'usage_count' => 0,
            'start_date' => null, 'end_date' => null, 'is_active' => true,
        ]);
    }

    // ------------------------------------------------------------------------------------------------------------------
    // Cancelar un pedido con reserva
    // ------------------------------------------------------------------------------------------------------------------

    public function test_cancelar_un_pedido_con_reserva_libera_el_central_y_el_cupon_sin_reponer_stock_local(): void
    {
        $producto = $this->producto(20);
        $cupon = $this->codigo();
        $pedido = $this->pedidoConReserva($producto, ['cart_discount_code' => 'DIEZ']);

        $this->assertSame(1, $cupon->fresh()->usage_count);
        $this->assertSame(20, $producto->fresh()->stock);
        $this->assertCount(1, $this->central->vivos());

        $this->cambiarEstado($pedido, 'cancelado')->assertSessionHasNoErrors();

        $pedido->refresh();
        $this->assertSame(EstadoOrden::Cancelado, $pedido->estado);
        $this->assertSame('cancelado', $pedido->rukoz_estado);
        $this->assertSame('Cancelado desde el panel de Chispas Frías', $pedido->motivo_cancelacion);

        // El Central liberó la reserva, con el motivo.
        $this->assertSame([], $this->central->vivos());
        $this->assertSame('Cancelado desde el panel de Chispas Frías', $this->central->pedidos[$pedido->rukoz_ref]['motivo']);

        // El cupón vuelve; el stock local ni se tocó (nunca se descontó) y no hay movimientos.
        $this->assertSame(0, $cupon->fresh()->usage_count);
        $this->assertNotNull($pedido->discount_usage_liberado_at);
        $this->assertSame(20, $producto->fresh()->stock);
        $this->assertSame(0, StockMovement::count());
    }

    public function test_cancelar_dos_veces_no_libera_dos_veces_el_cupon(): void
    {
        $cupon = $this->codigo();
        $pedido = $this->pedidoConReserva($this->producto(), ['cart_discount_code' => 'DIEZ']);

        $this->cambiarEstado($pedido, 'cancelado');
        $this->cambiarEstado($pedido, 'cancelado')->assertSessionHasErrors('estado'); // cancelado es terminal

        $this->assertSame(0, $cupon->fresh()->usage_count);
        $this->assertCount(1, $this->central->llamadasA('POST', '#/cancelar$#'));
    }

    public function test_cancelar_con_el_central_caido_deja_el_pedido_cancelacion_pendiente_y_se_resuelve_con_la_compensacion(): void
    {
        $cupon = $this->codigo();
        $producto = $this->producto();
        $pedido = $this->pedidoConReserva($producto, ['cart_discount_code' => 'DIEZ']);
        $this->central->caido = true;

        $this->cambiarEstado($pedido, 'cancelado')->assertSessionHasNoErrors();

        $pedido->refresh();
        $this->assertSame(EstadoOrden::Cancelado, $pedido->estado);
        $this->assertSame('cancelacion_pendiente', $pedido->rukoz_estado);
        $this->assertSame(0, $cupon->fresh()->usage_count);
        $this->assertCount(1, $this->central->vivos(), 'El Central todavía la tiene reservada');
        $this->assertSame(1, Compensacion::query()->pendientes()->where('tipo', Compensacion::CANCELAR)->where('ref', $pedido->rukoz_ref)->count());

        // Vuelve el Central y corre el cron de compensaciones: la reserva se libera y el pedido queda `cancelado`.
        $this->central->caido = false;
        $this->travel(2)->minutes();
        $this->artisan('rukoz:compensaciones')->assertSuccessful();

        $this->assertSame([], $this->central->vivos());
        $this->assertSame('cancelado', $pedido->fresh()->rukoz_estado);
        $this->assertSame(0, Compensacion::query()->pendientes()->count());
        $this->assertSame(20, $producto->fresh()->stock);
    }

    public function test_no_se_cancela_desde_la_web_un_pedido_que_el_central_ya_confirmo(): void
    {
        $pedido = $this->pedidoConReserva($this->producto());
        $this->central->pedidos[$pedido->rukoz_ref]['estado'] = 'confirmado';

        $this->cambiarEstado($pedido, 'cancelado')->assertSessionHasErrors('estado');

        $pedido->refresh();
        $this->assertSame(EstadoOrden::Pendiente, $pedido->estado);
        $this->assertSame('reservado', $pedido->rukoz_estado);
    }

    // ------------------------------------------------------------------------------------------------------------------
    // Despachado sigue siendo local hasta la 10.4
    // ------------------------------------------------------------------------------------------------------------------

    public function test_despachar_y_volver_a_pendiente_no_toca_el_stock_ni_llama_al_central(): void
    {
        $producto = $this->producto(20);
        $pedido = $this->pedidoConReserva($producto);
        $llamadasAntes = count($this->central->llamadas);

        $this->cambiarEstado($pedido, 'despachado')->assertSessionHasNoErrors();
        $this->assertSame(EstadoOrden::Despachado, $pedido->fresh()->estado);

        $this->cambiarEstado($pedido, 'pendiente')->assertSessionHasNoErrors();
        $this->assertSame(EstadoOrden::Pendiente, $pedido->fresh()->estado);

        $this->assertSame($llamadasAntes, count($this->central->llamadas));
        $this->assertSame(20, $producto->fresh()->stock);
        $this->assertSame('reservado', $pedido->fresh()->rukoz_estado);
        $this->assertCount(1, $this->central->vivos());
    }

    // ------------------------------------------------------------------------------------------------------------------
    // Los pedidos viejos (sin rukoz_ref) siguen con el flujo de hoy
    // ------------------------------------------------------------------------------------------------------------------

    public function test_cancelar_un_pedido_sin_rukoz_ref_repone_stock_local_aunque_el_interruptor_este_encendido(): void
    {
        $producto = $this->producto(20);
        $viejo = $this->pedidoViejo($producto);

        $this->assertNull($viejo->rukoz_ref);
        $this->assertSame(17, $producto->fresh()->stock); // descontó al crearse
        $this->central->llamadas = [];

        $this->cambiarEstado($viejo, 'cancelado')->assertSessionHasNoErrors();

        $this->assertSame(EstadoOrden::Cancelado, $viejo->fresh()->estado);
        $this->assertSame(20, $producto->fresh()->stock); // repuso
        $this->assertSame([], $this->central->llamadas, 'Un pedido de la web sola no se cancela en el Central');
    }

    public function test_los_dos_tipos_de_pedido_conviven_y_cada_uno_cancela_a_su_manera(): void
    {
        $producto = $this->producto(20);
        $viejo = $this->pedidoViejo($producto);                // 20 − 3 = 17 local
        $nuevo = $this->pedidoConReserva($producto);           // reserva en el Central, local intacto en 17

        $this->assertSame(17, $producto->fresh()->stock);

        $this->cambiarEstado($nuevo, 'cancelado');
        $this->assertSame(17, $producto->fresh()->stock, 'El pedido del Central no repone stock local');

        $this->cambiarEstado($viejo, 'cancelado');
        $this->assertSame(20, $producto->fresh()->stock);
    }

    public function test_el_stock_service_ignora_los_pedidos_con_rukoz_ref(): void
    {
        $producto = $this->producto(20);
        $pedido = $this->pedidoConReserva($producto);

        $servicio = app(StockService::class);
        $servicio->descontar($pedido);
        $servicio->reponer($pedido);

        $this->assertSame(20, $producto->fresh()->stock);
        $this->assertSame(0, StockMovement::count());
    }

    // ------------------------------------------------------------------------------------------------------------------
    // El admin no edita pedidos
    // ------------------------------------------------------------------------------------------------------------------

    public function test_el_admin_no_tiene_edicion_de_items(): void
    {
        // Documenta la decisión de la 10.3: el admin solo lista, mira y cambia el estado; no hay `editarReserva`.
        $rutas = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => str_starts_with((string) $r->getName(), 'admin.orders.'))
            ->map(fn ($r) => $r->getName())
            ->sort()->values()->all();

        $this->assertSame(['admin.orders.index', 'admin.orders.show', 'admin.orders.update-status'], $rutas);
    }

    public function test_el_detalle_del_admin_expone_la_reserva(): void
    {
        $pedido = $this->pedidoConReserva($this->producto());

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.orders.show', $pedido))
            ->assertInertia(fn ($page) => $page
                ->where('order.rukoz_ref', $pedido->rukoz_ref)
                ->where('order.rukoz_estado', 'reservado')
                ->where('order.motivo_cancelacion', null));
    }
}
