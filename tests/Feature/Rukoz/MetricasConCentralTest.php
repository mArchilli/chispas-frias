<?php

namespace Tests\Feature\Rukoz;

use App\Enums\EstadoOrden;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * 10.4 — Las métricas con ventas del Central. Un pedido con `rukoz_ref` es venta desde `confirmado_at` (confirmado,
 * empaquetado, despachado y entregado): los anulados, los cancelados y las reservas sin confirmar no suman (las reservas van a
 * «pendientes»). Los pedidos viejos (sin `rukoz_ref`) siguen como siempre: despachados, por su fecha de creación. Los días y los
 * meses son argentinos: la base guarda UTC y la medianoche es a las 03:00 UTC.
 */
class MetricasConCentralTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-15 15:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @param  string|null  $confirmadoUtc  `confirmado_at` (UTC, como se guarda); null = sin confirmar
     */
    private function delCentral(EstadoOrden $estado, float $total, string $creadoUtc, ?string $confirmadoUtc, string $titulo = 'Producto'): Order
    {
        $pedido = Order::factory()->create([
            'estado' => $estado, 'subtotal' => $total, 'total' => $total,
            'rukoz_ref' => 'CHF-'.strtoupper(bin2hex(random_bytes(6))),
            'rukoz_estado' => $estado === EstadoOrden::Pendiente ? 'reservado' : $estado->value,
            'rukoz_version' => 1, 'confirmado_at' => $confirmadoUtc,
        ]);
        $pedido->items()->create(['product_title' => $titulo, 'precio_unitario' => $total, 'cantidad' => 1, 'subtotal' => $total]);
        DB::table('orders')->where('id', $pedido->id)->update(['created_at' => $creadoUtc, 'updated_at' => $creadoUtc]);

        return $pedido;
    }

    private function viejo(EstadoOrden $estado, float $total, string $creadoUtc, string $titulo = 'Viejo'): Order
    {
        $pedido = Order::factory()->create(['estado' => $estado, 'subtotal' => $total, 'total' => $total]);
        $pedido->items()->create(['product_title' => $titulo, 'precio_unitario' => $total, 'cantidad' => 1, 'subtotal' => $total]);
        DB::table('orders')->where('id', $pedido->id)->update(['created_at' => $creadoUtc, 'updated_at' => $creadoUtc]);

        return $pedido;
    }

    /** El listado de pedidos de un mes (`2026-10`): trae las métricas y el desglose por día. */
    private function listado(string $mes = '2026-10')
    {
        return $this->actingAs(User::factory()->admin()->create())->get(route('admin.orders.index', ['month' => $mes]));
    }

    public function test_cuentan_confirmados_empaquetados_despachados_y_entregados_y_no_los_anulados_ni_las_reservas(): void
    {
        $this->delCentral(EstadoOrden::Confirmado, 1000, '2026-10-10 15:00:00', '2026-10-10 16:00:00');
        $this->delCentral(EstadoOrden::Empaquetado, 2000, '2026-10-10 15:00:00', '2026-10-10 16:00:00');
        $this->delCentral(EstadoOrden::Despachado, 3000, '2026-10-10 15:00:00', '2026-10-10 16:00:00');
        $this->delCentral(EstadoOrden::Entregado, 4000, '2026-10-10 15:00:00', '2026-10-10 16:00:00');
        $this->delCentral(EstadoOrden::Anulado, 50000, '2026-10-10 15:00:00', '2026-10-10 16:00:00');
        $this->delCentral(EstadoOrden::Cancelado, 60000, '2026-10-10 15:00:00', null);
        // Reservado sin confirmar: va a «pendientes», no a ventas.
        $this->delCentral(EstadoOrden::Pendiente, 700, '2026-10-10 15:00:00', null);

        $this->listado()->assertInertia(fn (Assert $page) => $page
            ->where('stats.revenue', 10000)
            ->where('stats.sales_count', 4)
            ->where('stats.avg_order_value', 2500)
            ->where('stats.pending_amount', 700)
            ->where('stats.pending_count', 1)
            ->where('stats.cancelled_count', 2) // cancelados y anulados se cuentan, nunca suman monto
            ->where('stats.orders_count', 7)
        );
    }

    public function test_la_venta_cuenta_el_dia_de_la_confirmacion_no_el_de_creacion(): void
    {
        // Creado el 30/09 pero confirmado el 02/10: es una venta de octubre.
        $this->delCentral(EstadoOrden::Confirmado, 8000, '2026-09-30 15:00:00', '2026-10-02 15:00:00');
        // Creado el 10/10 pero confirmado el 03/11: es una venta de noviembre.
        $this->delCentral(EstadoOrden::Confirmado, 500, '2026-10-10 15:00:00', '2026-11-03 15:00:00');

        $this->listado('2026-10')->assertInertia(fn (Assert $page) => $page->where('stats.revenue', 8000)->where('stats.sales_count', 1));
        $this->assertSame(0, $this->listado('2026-09')->viewData('page')['props']['stats']['sales_count']);
    }

    public function test_borde_de_medianoche_argentina_usa_confirmado_at_en_la_serie_diaria(): void
    {
        // Día 15/10 argentino = 15/10 03:00 UTC hasta 16/10 02:59:59 UTC.
        $this->delCentral(EstadoOrden::Confirmado, 100, '2026-10-01 12:00:00', '2026-10-15 02:59:59'); // 14/10 23:59:59 ARG → día 14
        $this->delCentral(EstadoOrden::Confirmado, 200, '2026-10-01 12:00:00', '2026-10-15 03:00:00'); // 15/10 00:00:00 ARG → día 15
        $this->delCentral(EstadoOrden::Confirmado, 400, '2026-10-01 12:00:00', '2026-10-16 02:59:59'); // 15/10 23:59:59 ARG → día 15
        $this->delCentral(EstadoOrden::Confirmado, 800, '2026-10-01 12:00:00', '2026-10-16 03:00:00'); // 16/10 00:00:00 ARG → día 16

        $this->listado()->assertInertia(fn (Assert $page) => $page
            ->where('stats.revenue', 1500)
            ->where('stats.sales_count', 4)
            ->where('dailyBreakdown.13.date', '2026-10-14')
            ->where('dailyBreakdown.13.revenue', 100)
            ->where('dailyBreakdown.14.date', '2026-10-15')
            ->where('dailyBreakdown.14.revenue', 600)
            ->where('dailyBreakdown.14.sales_count', 2)
            ->where('dailyBreakdown.15.date', '2026-10-16')
            ->where('dailyBreakdown.15.revenue', 800)
        );
    }

    public function test_borde_de_mes_argentino_usa_confirmado_at(): void
    {
        $this->delCentral(EstadoOrden::Confirmado, 300, '2026-10-01 12:00:00', '2026-11-01 02:59:59'); // 31/10 23:59:59 ARG → octubre
        $this->delCentral(EstadoOrden::Confirmado, 700, '2026-10-01 12:00:00', '2026-11-01 03:00:00'); // 01/11 00:00:00 ARG → noviembre

        $this->listado('2026-10')->assertInertia(fn (Assert $page) => $page
            ->where('stats.revenue', 300)
            ->where('dailyBreakdown.30.date', '2026-10-31')
            ->where('dailyBreakdown.30.revenue', 300)
        );

        Carbon::setTestNow('2026-11-15 15:00:00');
        $this->listado('2026-11')->assertInertia(fn (Assert $page) => $page
            ->where('stats.revenue', 700)
            ->where('dailyBreakdown.0.date', '2026-11-01')
            ->where('dailyBreakdown.0.revenue', 700)
        );
    }

    public function test_un_pedido_anulado_despues_de_confirmar_deja_de_contar(): void
    {
        $pedido = $this->delCentral(EstadoOrden::Confirmado, 9000, '2026-10-10 15:00:00', '2026-10-10 16:00:00', 'Pistola');
        $this->delCentral(EstadoOrden::Confirmado, 1000, '2026-10-10 15:00:00', '2026-10-10 16:00:00', 'Chispa');

        $this->listado()->assertInertia(fn (Assert $page) => $page->where('stats.revenue', 10000)->where('stats.top_product.title', 'Pistola'));

        $pedido->update(['estado' => EstadoOrden::Anulado, 'rukoz_estado' => 'anulado']);

        $this->listado()->assertInertia(fn (Assert $page) => $page
            ->where('stats.revenue', 1000)
            ->where('stats.sales_count', 1)
            ->where('stats.top_product.title', 'Chispa')
        );
    }

    public function test_un_despachado_manual_con_reserva_que_nunca_se_confirmo_no_es_una_venta(): void
    {
        // Pedido con rukoz_ref «despachado» a mano (10.3, antes de la confirmación) pero sin confirmar en el Central: el Central
        // no lo conoce como venta.
        $this->delCentral(EstadoOrden::Despachado, 5000, '2026-10-10 15:00:00', null);

        $this->listado()->assertInertia(fn (Assert $page) => $page->where('stats.revenue', 0)->where('stats.sales_count', 0));
    }

    public function test_los_pedidos_viejos_siguen_como_siempre_despachados_por_su_fecha_de_creacion(): void
    {
        $this->viejo(EstadoOrden::Despachado, 1000, '2026-10-10 15:00:00');
        $this->viejo(EstadoOrden::Pendiente, 500, '2026-10-10 15:00:00');
        $this->viejo(EstadoOrden::Cancelado, 9999, '2026-10-10 15:00:00');
        // Y conviven con uno del Central confirmado.
        $this->delCentral(EstadoOrden::Confirmado, 2000, '2026-10-12 15:00:00', '2026-10-12 16:00:00');

        $this->listado()->assertInertia(fn (Assert $page) => $page
            ->where('stats.revenue', 3000)
            ->where('stats.sales_count', 2)
            ->where('stats.pending_amount', 500)
            ->where('dailyBreakdown.9.revenue', 1000)  // el viejo, el día de su creación
            ->where('dailyBreakdown.11.revenue', 2000) // el del Central, el día de su confirmación
        );
    }

    public function test_el_listado_y_el_dashboard_usan_las_mismas_ventas(): void
    {
        $this->delCentral(EstadoOrden::Confirmado, 2500, '2026-10-12 15:00:00', '2026-10-12 16:00:00', 'Pistola');
        $this->delCentral(EstadoOrden::Anulado, 9000, '2026-10-12 15:00:00', '2026-10-12 16:00:00', 'Anulada');
        $this->delCentral(EstadoOrden::Pendiente, 700, '2026-10-12 15:00:00', null);

        $this->listado()->assertInertia(fn (Assert $page) => $page->where('stats.revenue', 2500)->where('stats.pending_amount', 700));

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.revenue_month', 2500)
                ->where('stats.sales_month_count', 1)
                ->where('stats.pending_month_amount', 700)
            );
    }

    public function test_el_producto_mas_vendido_sale_de_las_ventas_del_mes_por_fecha_de_confirmacion(): void
    {
        // Creada en septiembre pero confirmada en octubre: cuenta para octubre.
        $this->delCentral(EstadoOrden::Confirmado, 900, '2026-09-29 15:00:00', '2026-10-01 15:00:00', 'Bengala');
        // Creada en octubre pero sin confirmar: no cuenta.
        $this->delCentral(EstadoOrden::Pendiente, 100, '2026-10-02 15:00:00', null, 'Pote');

        $this->listado()->assertInertia(fn (Assert $page) => $page->where('stats.top_product.title', 'Bengala'));
    }
}
