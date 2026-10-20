<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoOrden;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Services\ResumenDePedidos;
use App\Support\ZonaHoraria;
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

    public function __construct(private readonly ResumenDePedidos $resumen) {}

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
            // Un producto con variantes está sin stock cuando ninguna variante activa lo tiene (Product::scopeOutOfStock).
            'out_of_stock' => Product::outOfStock()->count(),
            'low_stock' => Product::where('stock', '>', 0)
                ->where('stock', '<=', self::LOW_STOCK_THRESHOLD)
                ->count(),
            'offers_count' => ProductOffer::active()->count(),
            'discount_codes_active_count' => DiscountCode::active()->count(),
            'pending_orders_count' => Order::where('estado', EstadoOrden::Pendiente)->count(),
        ];
    }

    /**
     * Resumen del mes ARGENTINO en curso, con el mismo criterio que el panel de órdenes
     * (ResumenDePedidos): los ingresos y el ticket promedio salen solo de las ventas
     * (despachados); los pendientes se informan aparte y los cancelados nunca suman monto.
     */
    private function buildFinancialStats(): array
    {
        [$desde, $hasta] = ZonaHoraria::rangoDelMes(ZonaHoraria::ahora());
        $totales = $this->resumen->delRango($desde, $hasta);

        $avgOrderMonth = $totales['ventas_cantidad'] > 0
            ? $totales['ventas_monto'] / $totales['ventas_cantidad']
            : 0;

        return [
            // Todo lo recibido en el mes (cancelados incluidos).
            'orders_month_count' => $totales['pedidos'],
            'sales_month_count' => $totales['ventas_cantidad'],
            'revenue_month' => $totales['ventas_monto'],
            'formatted_revenue_month' => '$' . number_format($totales['ventas_monto'], 0, ',', '.'),
            'formatted_avg_order_month' => '$' . number_format($avgOrderMonth, 0, ',', '.'),
            'pending_month_count' => $totales['pendientes_cantidad'],
            'pending_month_amount' => $totales['pendientes_monto'],
            'formatted_pending_month_amount' => '$' . number_format($totales['pendientes_monto'], 0, ',', '.'),
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
                'created_at' => ZonaHoraria::formatear($order->created_at),
            ]);
    }
}
