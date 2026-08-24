<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminOrderVendorVisibilityTest extends TestCase
{
    use RefreshDatabase;

    // --- /admin/orders: panel de métricas financieras, exclusivo de admin --------

    public function test_vendedor_no_recibe_las_metricas_financieras_en_admin_orders(): void
    {
        $vendedor = User::factory()->create(['role' => RolUsuario::Vendedor]);

        $response = $this->actingAs($vendedor)->get(route('admin.orders.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Index')
            ->has('orders')
            ->has('filters')
            ->missing('stats')
            ->missing('dailyBreakdown')
            ->missing('month')
        );
    }

    public function test_admin_si_recibe_las_metricas_financieras_en_admin_orders(): void
    {
        $admin = User::factory()->create(['role' => RolUsuario::Admin]);

        $response = $this->actingAs($admin)->get(route('admin.orders.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Index')
            ->has('stats')
            ->has('dailyBreakdown')
            ->has('month')
        );
    }

    // --- /admin/dashboard: KPIs financieros y pedidos recientes, exclusivo de admin --

    public function test_vendedor_no_recibe_los_kpis_financieros_en_el_dashboard(): void
    {
        $vendedor = User::factory()->create(['role' => RolUsuario::Vendedor]);

        $response = $this->actingAs($vendedor)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Dashboard')
            ->has('stats.categories_count')
            ->has('stats.pending_orders_count')
            ->missing('stats.revenue_month')
            ->missing('stats.orders_month_count')
            ->missing('stats.formatted_revenue_month')
            ->missing('stats.formatted_avg_order_month')
            ->missing('recentOrders')
        );
    }

    public function test_admin_si_recibe_los_kpis_financieros_en_el_dashboard(): void
    {
        $admin = User::factory()->create(['role' => RolUsuario::Admin]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Dashboard')
            ->has('stats.categories_count')
            ->has('stats.revenue_month')
            ->has('stats.orders_month_count')
            ->has('stats.formatted_revenue_month')
            ->has('stats.formatted_avg_order_month')
            ->has('recentOrders')
        );
    }
}
