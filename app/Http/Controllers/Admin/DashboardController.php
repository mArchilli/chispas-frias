<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoOrden;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductOffer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Umbral de stock bajo. Debe coincidir con LOW_STOCK_THRESHOLD en
     * resources/js/utils/stock.js.
     */
    private const LOW_STOCK_THRESHOLD = 3;

    /**
     * Los KPIs operativos (catálogo, stock, pedidos pendientes) se calculan
     * y exponen para admin y vendedor por igual. Los financieros (ingresos,
     * ticket promedio, últimos pedidos con monto) son exclusivos de admin:
     * si el usuario es vendedor, esas queries ni se corren.
     */
    public function index(Request $request): Response
    {
        $props = [
            'stats' => $this->buildOperationalStats(),
        ];

        if ($request->user()->isAdmin()) {
            $props['stats'] = array_merge($props['stats'], $this->buildFinancialStats());
            $props['recentOrders'] = $this->buildRecentOrders();
        }

        return Inertia::render('Admin/Dashboard', $props);
    }

    private function buildOperationalStats(): array
    {
        return [
            'categories_count' => Category::count(),
            'products_count' => Product::active()->count(),
            'products_total' => Product::count(),
            'out_of_stock' => Product::where('stock', '<=', 0)->count(),
            'low_stock' => Product::where('stock', '>', 0)
                ->where('stock', '<=', self::LOW_STOCK_THRESHOLD)
                ->count(),
            'offers_count' => ProductOffer::active()->count(),
            'discount_codes_active_count' => DiscountCode::active()->count(),
            'pending_orders_count' => Order::where('estado', EstadoOrden::Pendiente)->count(),
        ];
    }

    private function buildFinancialStats(): array
    {
        $now = now();

        $revenueMonth = (float) Order::whereYear('created_at', $now->year)
            ->whereMonth('created_at', $now->month)
            ->where('estado', '!=', EstadoOrden::Cancelado)
            ->sum('total');

        $ordersMonthCount = Order::whereYear('created_at', $now->year)
            ->whereMonth('created_at', $now->month)
            ->count();

        $avgOrderMonth = $ordersMonthCount > 0 ? $revenueMonth / $ordersMonthCount : 0;

        return [
            'orders_month_count' => $ordersMonthCount,
            'revenue_month' => $revenueMonth,
            'formatted_revenue_month' => '$' . number_format($revenueMonth, 0, ',', '.'),
            'formatted_avg_order_month' => '$' . number_format($avgOrderMonth, 0, ',', '.'),
        ];
    }

    private function buildRecentOrders()
    {
        return Order::query()
            ->latest()
            ->take(5)
            ->get(['id', 'name', 'lastname', 'estado', 'total', 'created_at'])
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'name' => $order->name,
                'lastname' => $order->lastname,
                'estado' => $order->estado?->value,
                'formatted_total' => '$' . number_format((float) $order->total, 0, ',', '.'),
                'created_at' => $order->created_at->format('d/m/Y H:i'),
            ]);
    }
}
