<?php

namespace Tests\Feature\Rukoz;

use App\Enums\EstadoOrden;
use App\Models\Category;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\Product;
use App\Rukoz\EspejoStock;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Rukoz\CoreClient\Compensaciones\Compensacion;
use Rukoz\CoreClient\Eventos\StockActualizado;
use Tests\Support\CentralDePrueba;
use Tests\TestCase;

/**
 * 10.3 — `chispasfrias:cancelar-reservas-viejas`: las reservas del Central no vencen, así que la web cancela sola los pedidos
 * con `rukoz_ref` que llevan más de PEDIDOS_CANCELAR_DIAS (3) días sin confirmar. Tiempo congelado en todos los tests.
 */
class CancelarReservasViejasTest extends TestCase
{
    use RefreshDatabase;

    private CentralDePrueba $central;

    protected function setUp(): void
    {
        parent::setUp();

        $this->central = CentralDePrueba::instalar();
        Event::listen(StockActualizado::class, [EspejoStock::class, 'alActualizarse']);
        $this->travelTo(Carbon::parse('2026-10-20 12:00:00'));
    }

    protected function tearDown(): void
    {
        $this->assertSame([], $this->central->erroresDeContrato);

        parent::tearDown();
    }

    private int $secuencia = 0;

    /** Un pedido con reserva en el Central (por el checkout real), con la antigüedad pedida. */
    private function pedidoConReserva(Carbon $creado, int $cantidad = 1): Order
    {
        $producto = Product::factory()->for(Category::factory())->create(['title' => 'Chispa '.++$this->secuencia, 'price' => 1000, 'stock' => 20, 'is_active' => true]);

        $respuesta = $this->withSession(['cart' => [['product_id' => $producto->id, 'quantity' => $cantidad]]])
            ->postJson(route('cart.whatsapp'), ['customer_data' => [
                'name' => 'PRUEBA', 'lastname' => '10.3', 'dni' => '30111222', 'province' => 'buenos-aires', 'city' => 'La Plata',
                'postal_code' => '1900', 'phone' => '1100000000', 'email' => 'prueba@example.com',
            ]])->assertOk();

        $pedido = Order::findOrFail($respuesta->json('order_id'));
        Order::whereKey($pedido->id)->update(['created_at' => $creado]);

        return $pedido->fresh();
    }

    public function test_exactamente_tres_dias_no_cancela_y_un_segundo_despues_si(): void
    {
        $pedido = $this->pedidoConReserva(now()->subDays(3));

        $this->artisan('chispasfrias:cancelar-reservas-viejas')->assertSuccessful();
        $this->assertSame(EstadoOrden::Pendiente, $pedido->fresh()->estado, 'Con exactamente 3 días todavía no');
        $this->assertCount(1, $this->central->vivos());

        $this->travel(1)->seconds();

        $this->artisan('chispasfrias:cancelar-reservas-viejas')->assertSuccessful();
        $pedido->refresh();
        $this->assertSame(EstadoOrden::Cancelado, $pedido->estado);
        $this->assertSame('cancelado', $pedido->rukoz_estado);
        $this->assertSame('Cancelado automáticamente (3 días sin confirmar)', $pedido->motivo_cancelacion);
        $this->assertSame([], $this->central->vivos());
        $this->assertSame('Cancelado automáticamente (3 días sin confirmar)', $this->central->pedidos[$pedido->rukoz_ref]['motivo']);
    }

    public function test_un_pedido_reciente_no_se_toca(): void
    {
        $pedido = $this->pedidoConReserva(now()->subDays(2));

        $this->artisan('chispasfrias:cancelar-reservas-viejas')->assertSuccessful();

        $this->assertSame(EstadoOrden::Pendiente, $pedido->fresh()->estado);
        $this->assertCount(1, $this->central->vivos());
    }

    public function test_dry_run_lista_sin_cancelar_ni_llamar_al_central(): void
    {
        $pedido = $this->pedidoConReserva(now()->subDays(4));
        $llamadasAntes = count($this->central->llamadas);

        $this->artisan('chispasfrias:cancelar-reservas-viejas', ['--dry-run' => true])
            ->expectsOutputToContain($pedido->rukoz_ref)
            ->expectsOutputToContain('se cancelarían')
            ->assertSuccessful();

        $this->assertSame(EstadoOrden::Pendiente, $pedido->fresh()->estado);
        $this->assertSame('reservado', $pedido->fresh()->rukoz_estado);
        $this->assertSame($llamadasAntes, count($this->central->llamadas));
        $this->assertCount(1, $this->central->vivos());
    }

    public function test_la_opcion_dias_cambia_el_limite(): void
    {
        $pedido = $this->pedidoConReserva(now()->subDays(2)->subHour());

        $this->artisan('chispasfrias:cancelar-reservas-viejas')->assertSuccessful();
        $this->assertSame(EstadoOrden::Pendiente, $pedido->fresh()->estado);

        $this->artisan('chispasfrias:cancelar-reservas-viejas', ['--dias' => 2])->assertSuccessful();
        $this->assertSame(EstadoOrden::Cancelado, $pedido->fresh()->estado);
    }

    public function test_los_dias_salen_de_pedidos_cancelar_dias(): void
    {
        config(['integracion.cancelar_dias' => 5]);
        $pedido = $this->pedidoConReserva(now()->subDays(4));

        $this->artisan('chispasfrias:cancelar-reservas-viejas')->assertSuccessful();

        $this->assertSame(EstadoOrden::Pendiente, $pedido->fresh()->estado);
    }

    public function test_solo_cancela_pedidos_con_rukoz_ref_pendientes_y_con_reserva_viva(): void
    {
        $viejo = now()->subDays(10);

        $conRef = $this->pedidoConReserva($viejo);
        $despachado = $this->pedidoConReserva($viejo);
        $despachado->update(['estado' => EstadoOrden::Despachado]);
        $yaCancelado = $this->pedidoConReserva($viejo);
        $yaCancelado->update(['estado' => EstadoOrden::Cancelado, 'rukoz_estado' => 'cancelado']);
        // Un pedido de la web sola (stock local): nunca se cancela solo, por viejo que sea.
        $sinRef = Order::factory()->create(['estado' => EstadoOrden::Pendiente, 'created_at' => $viejo]);

        $this->artisan('chispasfrias:cancelar-reservas-viejas')->assertSuccessful();

        $this->assertSame(EstadoOrden::Cancelado, $conRef->fresh()->estado);
        $this->assertSame(EstadoOrden::Despachado, $despachado->fresh()->estado);
        $this->assertSame('reservado', $despachado->fresh()->rukoz_estado);
        $this->assertSame(EstadoOrden::Pendiente, $sinRef->fresh()->estado);
        $this->assertNull($sinRef->fresh()->rukoz_estado);
        // El Central solo recibió la cancelación del pedido que correspondía.
        $this->assertCount(1, $this->central->llamadasA('POST', '#/cancelar$#'));
    }

    public function test_libera_el_cupon_del_pedido_cancelado(): void
    {
        $cupon = DiscountCode::create(['code' => 'DIEZ', 'percentage' => 10, 'usage_limit' => 5, 'usage_count' => 0, 'is_active' => true]);
        $producto = Product::factory()->for(Category::factory())->create(['price' => 1000, 'stock' => 20, 'is_active' => true]);

        $respuesta = $this->withSession(['cart' => [['product_id' => $producto->id, 'quantity' => 1]], 'cart_discount_code' => 'DIEZ'])
            ->postJson(route('cart.whatsapp'), ['customer_data' => [
                'name' => 'PRUEBA', 'lastname' => '10.3', 'dni' => '30111222', 'province' => 'buenos-aires', 'city' => 'La Plata',
                'postal_code' => '1900', 'phone' => '1100000000', 'email' => 'prueba@example.com',
            ]])->assertOk();
        Order::whereKey($respuesta->json('order_id'))->update(['created_at' => now()->subDays(4)]);
        $this->assertSame(1, $cupon->fresh()->usage_count);

        $this->artisan('chispasfrias:cancelar-reservas-viejas')->assertSuccessful();

        $this->assertSame(0, $cupon->fresh()->usage_count);
        $this->assertSame(20, $producto->fresh()->stock);
    }

    public function test_con_el_central_caido_cancela_localmente_y_deja_la_compensacion(): void
    {
        $pedido = $this->pedidoConReserva(now()->subDays(4));
        $this->central->caido = true;

        $this->artisan('chispasfrias:cancelar-reservas-viejas')->assertSuccessful();

        $pedido->refresh();
        $this->assertSame(EstadoOrden::Cancelado, $pedido->estado);
        $this->assertSame('cancelacion_pendiente', $pedido->rukoz_estado);
        $this->assertSame(1, Compensacion::query()->pendientes()->where('tipo', Compensacion::CANCELAR)->count());
    }

    public function test_una_venta_confirmada_en_el_central_no_se_cancela_y_el_comando_falla(): void
    {
        $pedido = $this->pedidoConReserva(now()->subDays(4));
        $this->central->pedidos[$pedido->rukoz_ref]['estado'] = 'confirmado';

        $this->artisan('chispasfrias:cancelar-reservas-viejas')->assertFailed();

        $this->assertSame(EstadoOrden::Pendiente, $pedido->fresh()->estado);
    }

    public function test_sin_la_integracion_general_no_cancela_nada_pero_el_dry_run_lista(): void
    {
        $pedido = $this->pedidoConReserva(now()->subDays(4));
        config(['rukoz.habilitada' => false]);

        $this->artisan('chispasfrias:cancelar-reservas-viejas')->expectsOutputToContain('RUKOZ_INTEGRACION está apagado')->assertSuccessful();
        $this->assertSame(EstadoOrden::Pendiente, $pedido->fresh()->estado);

        $this->artisan('chispasfrias:cancelar-reservas-viejas', ['--dry-run' => true])->expectsOutputToContain($pedido->rukoz_ref)->assertSuccessful();
    }

    public function test_esta_programado_cada_hora_solo_con_la_integracion_encendida(): void
    {
        $evento = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->command, 'chispasfrias:cancelar-reservas-viejas'));

        $this->assertNotNull($evento, 'El comando tiene que estar programado en routes/console.php');
        $this->assertSame('0 * * * *', $evento->expression);
        $this->assertTrue($evento->filtersPass(app()));

        config(['rukoz.habilitada' => false]);
        $this->assertFalse($evento->filtersPass(app()));
    }
}
