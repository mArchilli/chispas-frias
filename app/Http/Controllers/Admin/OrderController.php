<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoOrden;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\StockService;
use App\Support\Provincias;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    private const MESES = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
        7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    /**
     * Display a listing of orders: cola operativa (filtrada por estado, sin
     * recorte de fecha) + métricas de negocio del mes seleccionado.
     */
    public function index(Request $request): Response
    {
        $search = $request->get('search');
        $estadoFilter = $request->get('estado', EstadoOrden::Pendiente->value);

        if (!in_array($estadoFilter, array_column(EstadoOrden::cases(), 'value'), true)) {
            $estadoFilter = EstadoOrden::Pendiente->value;
        }

        $query = Order::query()->where('estado', $estadoFilter);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('lastname', 'like', "%{$search}%")
                  ->orWhere('dni', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $orders = $query->withCount('items')
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Order $order) => [
                'id' => $order->id,
                'name' => $order->name,
                'lastname' => $order->lastname,
                'estado' => $order->estado->value,
                'total' => (float) $order->total,
                'formatted_total' => '$' . number_format((float) $order->total, 0, ',', '.'),
                'items_count' => $order->items_count,
                'province' => Provincias::nombre($order->province),
                'city' => $order->city,
                'created_at' => $order->created_at->format('d/m/Y H:i'),
            ]);

        [$monthStart, $monthEnd, $monthMeta] = $this->resolveMonth($request->get('month'));

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders,
            'filters' => [
                'search' => $search ?? '',
                'estado' => $estadoFilter,
            ],
            'stats' => $this->buildMonthStats($monthStart, $monthEnd),
            'dailyBreakdown' => $this->buildDailyBreakdown($monthStart, $monthEnd),
            'month' => $monthMeta,
        ]);
    }

    /**
     * Display the specified order.
     */
    public function show(Order $order): Response
    {
        $order->load('items.product.images');

        $transicionesDisponibles = collect(EstadoOrden::cases())
            ->filter(fn (EstadoOrden $destino) => $order->estado->puedeTransicionarA($destino))
            ->map(fn (EstadoOrden $destino) => $destino->value)
            ->values();

        return Inertia::render('Admin/Orders/Show', [
            'order' => [
                'id' => $order->id,
                'name' => $order->name,
                'lastname' => $order->lastname,
                'dni' => $order->dni,
                'province' => $order->province,
                'province_label' => Provincias::nombre($order->province),
                'city' => $order->city,
                'address' => $order->address,
                'number' => $order->number,
                'between_streets' => $order->between_streets,
                'postal_code' => $order->postal_code,
                'phone' => $order->phone,
                'email' => $order->email,
                'observations' => $order->observations,
                'estado' => $order->estado->value,
                'total' => (float) $order->total,
                'formatted_total' => '$' . number_format((float) $order->total, 0, ',', '.'),
                'mensaje_whatsapp' => $order->mensaje_whatsapp,
                'created_at' => $order->created_at->format('d/m/Y H:i'),
                'items' => $order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_title' => $item->product_title,
                    'cantidad' => $item->cantidad,
                    'precio_unitario' => (float) $item->precio_unitario,
                    'subtotal' => (float) $item->subtotal,
                    'primary_image' => $item->product?->primaryImage()?->path,
                    'product_active' => (bool) ($item->product?->is_active ?? false),
                ]),
                'transiciones_disponibles' => $transicionesDisponibles,
            ],
        ]);
    }

    /**
     * Actualizar el estado de una orden.
     */
    public function updateStatus(Request $request, Order $order, StockService $stockService): RedirectResponse
    {
        $validated = $request->validate([
            'estado' => ['required', 'string', Rule::enum(EstadoOrden::class)],
        ]);

        $nuevoEstado = EstadoOrden::from($validated['estado']);

        if (! $order->estado->puedeTransicionarA($nuevoEstado)) {
            return back()->withErrors([
                'estado' => $this->mensajeTransicionInvalida($order->estado, $nuevoEstado),
            ]);
        }

        // La única transición que repone stock es pendiente -> cancelado. El chequeo se
        // hace acá, antes del update(), porque este último modifica $order->estado en
        // memoria.
        $esCancelacionDesdePendiente = $order->estado === EstadoOrden::Pendiente
            && $nuevoEstado === EstadoOrden::Cancelado;

        try {
            DB::transaction(function () use ($order, $nuevoEstado, $esCancelacionDesdePendiente, $stockService) {
                $order->update(['estado' => $nuevoEstado]);

                if ($esCancelacionDesdePendiente) {
                    $stockService->reponer($order);
                }
            });
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors([
                'estado' => 'No pudimos actualizar la orden. Por favor, intentá nuevamente.',
            ]);
        }

        return back()->with('success', "Orden actualizada a \"{$nuevoEstado->value}\".");
    }

    /**
     * Mensaje de error específico según la transición rechazada.
     */
    private function mensajeTransicionInvalida(EstadoOrden $actual, EstadoOrden $destino): string
    {
        if ($actual === EstadoOrden::Cancelado) {
            return 'No se puede modificar una orden cancelada.';
        }

        if ($actual === EstadoOrden::Despachado && $destino === EstadoOrden::Cancelado) {
            return 'No se puede cancelar una orden despachada. Primero volvé la orden a pendiente.';
        }

        return "No se puede pasar la orden de \"{$actual->value}\" a \"{$destino->value}\".";
    }

    /**
     * Resuelve el mes pedido por query string (?month=YYYY-MM) a su rango de
     * fechas y metadata de navegación. Nunca deja avanzar a un mes futuro:
     * si piden uno posterior al actual, se clampa al actual.
     *
     * @return array{0: Carbon, 1: Carbon, 2: array}
     */
    private function resolveMonth(?string $monthParam): array
    {
        $current = now()->startOfMonth();
        $requested = $current->copy();

        if ($monthParam) {
            try {
                $requested = Carbon::createFromFormat('Y-m', $monthParam)->startOfMonth();
            } catch (\Throwable $e) {
                $requested = $current->copy();
            }
        }

        if ($requested->gt($current)) {
            $requested = $current->copy();
        }

        $start = $requested->copy()->startOfMonth();
        $end = $requested->copy()->endOfMonth();
        $next = $requested->copy()->addMonth();

        $meta = [
            'year' => $requested->year,
            'month' => $requested->month,
            'value' => $requested->format('Y-m'),
            'label' => self::MESES[$requested->month] . ' ' . $requested->year,
            'prev' => $requested->copy()->subMonth()->format('Y-m'),
            'next' => $next->format('Y-m'),
            'can_go_next' => $next->lte($current),
            'is_current' => $requested->isSameMonth($current),
        ];

        return [$start, $end, $meta];
    }

    /**
     * Métricas de negocio del mes: ingresos (sin canceladas), ticket
     * promedio, producto más vendido y provincias más solicitadas (proxy de
     * "tipo de envío" ya que el checkout no distingue métodos de envío).
     */
    private function buildMonthStats(Carbon $start, Carbon $end): array
    {
        $ordersCount = Order::whereBetween('created_at', [$start, $end])->count();
        $cancelledCount = Order::whereBetween('created_at', [$start, $end])
            ->where('estado', EstadoOrden::Cancelado)
            ->count();

        $revenue = (float) Order::whereBetween('created_at', [$start, $end])
            ->where('estado', '!=', EstadoOrden::Cancelado)
            ->sum('total');

        $paidCount = $ordersCount - $cancelledCount;
        $avgOrderValue = $paidCount > 0 ? $revenue / $paidCount : 0;

        $topProduct = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.created_at', [$start, $end])
            ->where('orders.estado', '!=', EstadoOrden::Cancelado->value)
            ->select('order_items.product_title')
            ->selectRaw('SUM(order_items.cantidad) as total_qty')
            ->groupBy('order_items.product_title')
            ->orderByDesc('total_qty')
            ->first();

        $topLocations = Order::whereBetween('created_at', [$start, $end])
            ->where('estado', '!=', EstadoOrden::Cancelado)
            ->select('province')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('province')
            ->orderByDesc('total')
            ->limit(3)
            ->get()
            ->map(fn ($row) => [
                'province' => Provincias::nombre($row->province),
                'count' => (int) $row->total,
            ])
            ->values();

        return [
            'orders_count' => $ordersCount,
            'cancelled_count' => $cancelledCount,
            'revenue' => $revenue,
            'formatted_revenue' => '$' . number_format($revenue, 0, ',', '.'),
            'avg_order_value' => $avgOrderValue,
            'formatted_avg_order_value' => '$' . number_format($avgOrderValue, 0, ',', '.'),
            'top_product' => $topProduct ? [
                'title' => $topProduct->product_title,
                'quantity' => (int) $topProduct->total_qty,
            ] : null,
            'top_locations' => $topLocations,
        ];
    }

    /**
     * Serie diaria del mes completo (1..último día) para el gráfico de
     * "pedidos por día". orders_count cuenta todas las órdenes recibidas ese
     * día (cualquier estado); revenue solo las no canceladas, mismo criterio
     * que buildMonthStats().
     */
    private function buildDailyBreakdown(Carbon $start, Carbon $end): array
    {
        $rows = Order::whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) as day')
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('SUM(CASE WHEN estado != ? THEN total ELSE 0 END) as revenue', [EstadoOrden::Cancelado->value])
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $days = collect();
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $row = $rows->get($key);
            $revenue = (float) ($row->revenue ?? 0);

            $days->push([
                'day' => $cursor->day,
                'date' => $key,
                'orders_count' => (int) ($row->orders_count ?? 0),
                'revenue' => $revenue,
                'formatted_revenue' => '$' . number_format($revenue, 0, ',', '.'),
            ]);

            $cursor->addDay();
        }

        return $days->values()->all();
    }
}
