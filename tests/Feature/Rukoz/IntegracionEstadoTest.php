<?php

namespace Tests\Feature\Rukoz;

use App\Enums\EstadoOrden;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Rukoz\CoreClient\Circuito\Circuito;
use Rukoz\CoreClient\Compensaciones\Compensacion;
use Rukoz\CoreClient\Stock\StockItem;
use Tests\Support\CentralDePrueba;
use Tests\TestCase;

/**
 * 10.3 — `chispasfrias:integracion-estado`: interruptores, ping, ítems sin espejo, compensaciones y reservas viejas.
 * Sale con código ≠ 0 si algo está mal.
 */
class IntegracionEstadoTest extends TestCase
{
    use RefreshDatabase;

    private CentralDePrueba $central;

    protected function setUp(): void
    {
        parent::setUp();

        $this->central = CentralDePrueba::instalar();
    }

    /** Un producto activo con su fila de espejo al día (para que el estado base sea sano). */
    private function productoConEspejo(int $stock = 5): Product
    {
        $producto = Product::factory()->for(Category::factory())->create(['stock' => $stock, 'is_active' => true]);
        StockItem::query()->create(['tipo' => 'producto', 'external_id' => (string) $producto->id, 'disponible' => $stock, 'vinculado' => true, 'version' => 1]);

        return $producto;
    }

    private function reservaDe(string $ref, \DateTimeInterface $creada, EstadoOrden $estado = EstadoOrden::Pendiente, string $rukozEstado = 'reservado'): Order
    {
        $pedido = Order::factory()->create(['estado' => $estado, 'rukoz_ref' => $ref, 'rukoz_estado' => $rukozEstado]);
        Order::whereKey($pedido->id)->update(['created_at' => $creada]);

        return $pedido;
    }

    public function test_todo_en_orden_sale_con_cero_y_muestra_los_interruptores_y_el_ping(): void
    {
        $this->productoConEspejo();

        $this->artisan('chispasfrias:integracion-estado')
            ->expectsOutputToContain('RUKOZ_INTEGRACION')
            ->expectsOutputToContain('el checkout USA el Central')
            ->expectsOutputToContain('Ping al Central')
            ->expectsOutputToContain('Ítems activos sin espejo')
            ->expectsOutputToContain('Reservas pendientes de compensar')
            ->assertSuccessful();
    }

    public function test_con_todo_apagado_tambien_sale_bien(): void
    {
        config(['rukoz.habilitada' => false, 'integracion.stock' => false, 'integracion.checkout' => false]);

        $this->artisan('chispasfrias:integracion-estado')
            ->expectsOutputToContain('omitido (RUKOZ_INTEGRACION apagado)')
            ->assertSuccessful();
    }

    public function test_checkout_encendido_sin_el_espejo_de_stock_es_un_error(): void
    {
        config(['integracion.stock' => false]);

        $this->artisan('chispasfrias:integracion-estado')
            ->expectsOutputToContain('falta INTEGRACION_STOCK')
            ->expectsOutputToContain('el checkout NO usa el Central')
            ->assertFailed();
    }

    public function test_checkout_encendido_sin_la_integracion_general_es_un_error(): void
    {
        config(['rukoz.habilitada' => false]);

        $this->artisan('chispasfrias:integracion-estado')->expectsOutputToContain('falta RUKOZ_INTEGRACION')->assertFailed();
    }

    public function test_items_sin_espejo_con_el_checkout_encendido_es_un_error(): void
    {
        Product::factory()->for(Category::factory())->create(['stock' => 5, 'is_active' => true]); // sin fila en rukoz_stock

        $this->artisan('chispasfrias:integracion-estado')->expectsOutputToContain('Ítems activos sin espejo')->assertFailed();
    }

    public function test_items_sin_espejo_con_el_checkout_apagado_solo_se_informa(): void
    {
        config(['integracion.checkout' => false]);
        Product::factory()->for(Category::factory())->create(['stock' => 5, 'is_active' => true]);

        $this->artisan('chispasfrias:integracion-estado')->expectsOutputToContain('Ítems activos sin espejo')->assertSuccessful();
    }

    public function test_el_central_caido_es_un_error(): void
    {
        $this->productoConEspejo();
        $this->central->caido = true;

        $this->artisan('chispasfrias:integracion-estado')->expectsOutputToContain('FALLA')->assertFailed();
    }

    public function test_el_circuito_abierto_es_un_error(): void
    {
        $this->productoConEspejo();

        for ($i = 0; $i < 5; $i++) {
            app(Circuito::class)->falla();
        }

        $this->artisan('chispasfrias:integracion-estado')->assertFailed();
    }

    public function test_reservas_viejas_se_listan_y_son_un_error(): void
    {
        $this->productoConEspejo();
        $this->reservaDe('CHF-RECIENTE', now()->subDays(2));
        $this->reservaDe('CHF-VIEJA', now()->subDays(4));
        $this->reservaDe('CHF-DESPACHADA', now()->subDays(9), EstadoOrden::Despachado); // no es una reserva abierta

        $this->artisan('chispasfrias:integracion-estado')
            ->expectsOutputToContain('Pedidos con reserva abierta')
            ->expectsOutputToContain('los cancela el cron')
            ->assertFailed();
    }

    public function test_compensaciones_pendientes_se_cuentan_por_tipo_pero_no_son_un_error(): void
    {
        $this->productoConEspejo();
        $pedido = $this->reservaDe('CHF-PEND', now());
        Compensacion::query()->create(['ref' => $pedido->rukoz_ref, 'tipo' => Compensacion::CANCELAR, 'estado' => Compensacion::PENDIENTE, 'intentos' => 1]);

        $this->artisan('chispasfrias:integracion-estado')
            ->expectsOutputToContain('cancelar: 1')
            ->assertSuccessful();
    }

    public function test_compensaciones_fallidas_son_un_error(): void
    {
        $this->productoConEspejo();
        Compensacion::query()->create(['ref' => 'CHF-FALLIDA', 'tipo' => Compensacion::CANCELAR, 'estado' => Compensacion::FALLIDA, 'intentos' => 12]);

        $this->artisan('chispasfrias:integracion-estado')->expectsOutputToContain('requieren una persona')->assertFailed();
    }

    public function test_cancelaciones_pendientes_se_informan(): void
    {
        $this->productoConEspejo();
        $this->reservaDe('CHF-CANC', now(), EstadoOrden::Cancelado, 'cancelacion_pendiente');

        $this->artisan('chispasfrias:integracion-estado')
            ->expectsOutputToContain('Pedidos cancelados esperando que el Central confirme')
            ->assertSuccessful();
    }

    // ------------------------------------------------------------------------------------------------------------------
    // 10.4 — Ventas
    // ------------------------------------------------------------------------------------------------------------------

    public function test_el_bloque_de_ventas_cuenta_confirmadas_saldos_y_vendedores_sin_mapeo(): void
    {
        config(['integracion.confirmacion' => true]);
        \Illuminate\Support\Facades\Event::listen(\Rukoz\CoreClient\Eventos\PedidoActualizado::class, [\App\Rukoz\EstadoDesdeCentral::class, 'alActualizarse']);
        $this->productoConEspejo();
        \App\Models\User::factory()->admin()->create(['rukoz_vendedor_id' => '7']);
        \App\Models\User::factory()->vendedor()->create(['rukoz_vendedor_id' => null]);
        Order::factory()->create(['estado' => EstadoOrden::Confirmado, 'rukoz_ref' => 'CHF-A', 'rukoz_estado' => 'confirmado', 'confirmado_at' => now(), 'rukoz_saldo' => 500]);
        Order::factory()->create(['estado' => EstadoOrden::Despachado, 'rukoz_ref' => 'CHF-B', 'rukoz_estado' => 'despachado', 'confirmado_at' => now(), 'rukoz_saldo' => 0]);

        $this->artisan('chispasfrias:integracion-estado')
            ->expectsOutputToContain('INTEGRACION_CONFIRMACION')
            ->expectsOutputToContain('confirmado: 1, despachado: 1')
            ->expectsOutputToContain('1 de 2')
            ->expectsOutputToContain('registrado')
            ->assertSuccessful();
    }

    public function test_la_confirmacion_sin_el_listener_registrado_es_un_error(): void
    {
        config(['integracion.confirmacion' => true]);
        $this->productoConEspejo();

        // La app arrancó con el interruptor apagado, así que el listener de estados no está registrado.
        $this->artisan('chispasfrias:integracion-estado')
            ->expectsOutputToContain('NO registrado')
            ->assertFailed();
    }
}
