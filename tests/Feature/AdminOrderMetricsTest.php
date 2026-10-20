<?php

namespace Tests\Feature;

use App\Enums\EstadoOrden;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Métricas de /admin/orders y del dashboard:
 *  - VENTAS = pedidos despachados (EstadoOrden::deVenta()); son el único ingreso.
 *  - PENDIENTES = monto y cantidad aparte, nunca mezclados con las ventas.
 *  - CANCELADOS = se cuentan, nunca suman monto.
 * Y los días / meses son días / meses ARGENTINOS (la base guarda UTC): el borde de medianoche
 * es a las 03:00 UTC.
 */
class AdminOrderMetricsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** Pedido con fecha de creación en UTC (como se guarda). */
    private function pedido(string $estado, float $total, string $creadoUtc, array $extra = []): Order
    {
        return Order::factory()->create(array_merge([
            'estado' => $estado,
            'total' => $total,
            'province' => 'buenos-aires',
            'created_at' => Carbon::parse($creadoUtc, 'UTC'),
            'updated_at' => Carbon::parse($creadoUtc, 'UTC'),
        ], $extra));
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    // --- ventas / pendientes / cancelados ---------------------------------------------

    public function test_las_ventas_son_los_despachados_y_los_pendientes_van_aparte(): void
    {
        Carbon::setTestNow('2026-10-15 15:00:00');

        $this->pedido('despachado', 1000, '2026-10-05 15:00:00');
        $this->pedido('despachado', 500, '2026-10-06 15:00:00');
        $this->pedido('pendiente', 300, '2026-10-07 15:00:00');
        $this->pedido('pendiente', 200, '2026-10-08 15:00:00');
        $this->pedido('cancelado', 9999, '2026-10-09 15:00:00');

        $this->actingAs($this->admin())->get(route('admin.orders.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.orders_count', 5)          // todo lo recibido
                ->where('stats.sales_count', 2)           // despachados
                ->where('stats.revenue', 1500)            // solo ventas: ni pendientes ni cancelados
                ->where('stats.formatted_revenue', '$1.500')
                ->where('stats.avg_order_value', 750)     // ticket de las ventas
                ->where('stats.pending_count', 2)
                ->where('stats.pending_amount', 500)
                ->where('stats.formatted_pending_amount', '$500')
                ->where('stats.cancelled_count', 1)
                ->where('month.value', '2026-10')
            );
    }

    public function test_un_pedido_cancelado_nunca_suma_monto_en_ningun_total(): void
    {
        Carbon::setTestNow('2026-10-15 15:00:00');

        $this->pedido('cancelado', 5000, '2026-10-09 15:00:00');

        $this->actingAs($this->admin())->get(route('admin.orders.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.revenue', 0)
                ->where('stats.pending_amount', 0)
                ->where('stats.cancelled_count', 1)
                ->where('stats.avg_order_value', 0)
                ->where('dailyBreakdown.8.orders_count', 1)   // el 9: se cuenta como recibido…
                ->where('dailyBreakdown.8.revenue', 0)        // …pero no suma ingresos
                ->where('dailyBreakdown.8.pending_amount', 0)
            );
    }

    public function test_el_desglose_diario_separa_ventas_y_pendientes(): void
    {
        Carbon::setTestNow('2026-10-15 15:00:00');

        $this->pedido('despachado', 1000, '2026-10-05 15:00:00');
        $this->pedido('pendiente', 300, '2026-10-05 16:00:00');
        $this->pedido('cancelado', 700, '2026-10-05 17:00:00');

        $this->actingAs($this->admin())->get(route('admin.orders.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('dailyBreakdown', 31)
                ->where('dailyBreakdown.4.date', '2026-10-05')
                ->where('dailyBreakdown.4.orders_count', 3)
                ->where('dailyBreakdown.4.sales_count', 1)
                ->where('dailyBreakdown.4.revenue', 1000)
                ->where('dailyBreakdown.4.pending_amount', 300)
            );
    }

    public function test_el_producto_mas_vendido_sale_solo_de_las_ventas(): void
    {
        Carbon::setTestNow('2026-10-15 15:00:00');

        $vendido = $this->pedido('despachado', 100, '2026-10-05 15:00:00');
        $pendiente = $this->pedido('pendiente', 100, '2026-10-05 16:00:00');
        $cancelado = $this->pedido('cancelado', 100, '2026-10-05 17:00:00');

        foreach ([[$vendido, 'Chispa', 2], [$pendiente, 'Volcán', 50], [$cancelado, 'Bengala', 90]] as [$orden, $titulo, $cantidad]) {
            $orden->items()->create([
                'product_title' => $titulo, 'cantidad' => $cantidad,
                'precio_unitario' => 1, 'base_unit_price' => 1, 'subtotal' => $cantidad,
            ]);
        }

        $this->actingAs($this->admin())->get(route('admin.orders.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.top_product.title', 'Chispa')
                ->where('stats.top_product.quantity', 2)
            );
    }

    public function test_el_dashboard_usa_el_mismo_criterio(): void
    {
        Carbon::setTestNow('2026-10-15 15:00:00');

        $this->pedido('despachado', 1000, '2026-10-05 15:00:00');
        $this->pedido('despachado', 500, '2026-10-06 15:00:00');
        $this->pedido('pendiente', 300, '2026-10-07 15:00:00');
        $this->pedido('cancelado', 9999, '2026-10-09 15:00:00');
        $this->pedido('despachado', 77777, '2026-09-20 15:00:00'); // mes anterior

        $this->actingAs($this->admin())->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.orders_month_count', 4)
                ->where('stats.sales_month_count', 2)
                ->where('stats.revenue_month', 1500)
                ->where('stats.formatted_revenue_month', '$1.500')
                ->where('stats.formatted_avg_order_month', '$750')
                ->where('stats.pending_month_count', 1)
                ->where('stats.pending_month_amount', 300)
                ->where('stats.formatted_pending_month_amount', '$300')
            );
    }

    // --- borde de medianoche (hora argentina) ---------------------------------------------

    public function test_un_pedido_de_las_22_del_ultimo_dia_del_mes_es_de_ese_mes_aunque_en_utc_sea_del_siguiente(): void
    {
        Carbon::setTestNow('2026-11-10 15:00:00');

        // 2026-11-01 01:30 UTC = 2026-10-31 22:30 en Buenos Aires.
        $this->pedido('despachado', 1000, '2026-11-01 01:30:00');

        $this->actingAs($this->admin())->get(route('admin.orders.index', ['month' => '2026-10']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.revenue', 1000)
                ->where('dailyBreakdown.30.date', '2026-10-31')
                ->where('dailyBreakdown.30.orders_count', 1)
            );

        $this->actingAs($this->admin())->get(route('admin.orders.index', ['month' => '2026-11']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.revenue', 0)
                ->where('stats.orders_count', 0)
                ->where('dailyBreakdown.0.orders_count', 0)
            );
    }

    public function test_el_corte_exacto_del_mes_es_a_las_03_utc(): void
    {
        Carbon::setTestNow('2026-11-10 15:00:00');

        $this->pedido('despachado', 100, '2026-11-01 02:59:59'); // 23:59:59 del 31/10 argentino
        $this->pedido('despachado', 200, '2026-11-01 03:00:00'); // 00:00:00 del 1/11 argentino
        $this->pedido('despachado', 400, '2026-10-01 02:59:59'); // 23:59:59 del 30/09 argentino
        $this->pedido('despachado', 800, '2026-10-01 03:00:00'); // 00:00:00 del 1/10 argentino

        $octubre = $this->actingAs($this->admin())->get(route('admin.orders.index', ['month' => '2026-10']));
        $octubre->assertInertia(fn (Assert $page) => $page
            ->where('stats.revenue', 900)                     // 100 + 800
            ->where('dailyBreakdown.0.date', '2026-10-01')
            ->where('dailyBreakdown.0.revenue', 800)
            ->where('dailyBreakdown.30.revenue', 100)
        );

        $this->actingAs($this->admin())->get(route('admin.orders.index', ['month' => '2026-11']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.revenue', 200)
                ->where('dailyBreakdown.0.revenue', 200)
            );

        $this->actingAs($this->admin())->get(route('admin.orders.index', ['month' => '2026-09']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.revenue', 400)
                ->where('dailyBreakdown.29.date', '2026-09-30')
                ->where('dailyBreakdown.29.revenue', 400)
            );
    }

    public function test_el_dashboard_toma_el_mes_argentino_en_curso(): void
    {
        // 2026-11-01 01:00 UTC = 2026-10-31 22:00 en Buenos Aires: todavía es octubre.
        Carbon::setTestNow('2026-11-01 01:00:00');

        $this->pedido('despachado', 100, '2026-10-31 15:00:00');
        $this->pedido('despachado', 200, '2026-11-01 00:30:00'); // 21:30 del 31/10 argentino
        $this->pedido('despachado', 900, '2026-09-30 15:00:00');

        $this->actingAs($this->admin())->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.revenue_month', 300)
                ->where('stats.sales_month_count', 2)
            );
    }

    public function test_el_mes_por_defecto_es_el_argentino(): void
    {
        // Es 1° de noviembre en UTC pero todavía 31 de octubre en Buenos Aires.
        Carbon::setTestNow('2026-11-01 01:00:00');

        $this->actingAs($this->admin())->get(route('admin.orders.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('month.value', '2026-10')
                ->where('month.is_current', true)
                ->where('month.can_go_next', false)
            );
    }

    public function test_pedir_un_mes_un_dia_31_no_desborda_al_mes_siguiente(): void
    {
        Carbon::setTestNow('2026-10-31 15:00:00');

        $this->actingAs($this->admin())->get(route('admin.orders.index', ['month' => '2026-02']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('month.value', '2026-02')
                ->has('dailyBreakdown', 28)
            );
    }

    public function test_un_mes_futuro_se_clampa_al_actual(): void
    {
        Carbon::setTestNow('2026-10-15 15:00:00');

        $this->actingAs($this->admin())->get(route('admin.orders.index', ['month' => '2027-03']))
            ->assertInertia(fn (Assert $page) => $page->where('month.value', '2026-10'));
    }

    // --- fechas que se muestran ----------------------------------------------------------

    public function test_las_fechas_del_listado_y_del_detalle_se_muestran_en_hora_argentina(): void
    {
        Carbon::setTestNow('2026-10-20 15:00:00');

        // 2026-10-19 02:30 UTC = 2026-10-18 23:30 en Buenos Aires.
        $orden = $this->pedido('pendiente', 100, '2026-10-19 02:30:00');
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.orders.index'))
            ->assertInertia(fn (Assert $page) => $page->where('orders.data.0.created_at', '18/10/2026 23:30'));

        $this->actingAs($admin)->get(route('admin.orders.show', $orden))
            ->assertInertia(fn (Assert $page) => $page->where('order.created_at', '18/10/2026 23:30'));

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('recentOrders.0.created_at', '18/10/2026 23:30'));
    }

    public function test_los_estados_de_venta_y_pendientes_estan_definidos_en_un_solo_lugar(): void
    {
        $this->assertSame([EstadoOrden::Despachado], EstadoOrden::deVenta());
        $this->assertSame([EstadoOrden::Pendiente], EstadoOrden::pendientes());
        $this->assertNotContains(EstadoOrden::Cancelado, [...EstadoOrden::deVenta(), ...EstadoOrden::pendientes()]);
    }
}
