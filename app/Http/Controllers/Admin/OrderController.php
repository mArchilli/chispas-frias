<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoOrden;
use App\Enums\TipoEntrega;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Rukoz\DatosDeEnvio;
use App\Rukoz\DatosDelCliente;
use App\Rukoz\ReservaDePedido;
use App\Services\DiscountCodeService;
use App\Services\ResumenDePedidos;
use App\Services\StockService;
use App\Support\Provincias;
use App\Support\ZonaHoraria;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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
     * recorte de fecha, igual para admin y vendedor) + métricas de negocio
     * del mes seleccionado (ingresos, ticket promedio, etc.) — esa parte es
     * exclusiva de admin, así que ni se calcula para un vendedor.
     */
    public function index(Request $request, ResumenDePedidos $resumen): Response
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
                  ->orWhere('phone', 'like', "%{$search}%")
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
                'nombre_completo' => $order->nombreCompleto(),
                'estado' => $order->estado->value,
                'total' => (float) $order->total,
                'formatted_total' => '$' . number_format((float) $order->total, 0, ',', '.'),
                'items_count' => $order->items_count,
                // Un retiro en oficina no tiene provincia: se muestra la oficina.
                'province' => $order->esRetiro() ? TipoEntrega::OFICINA : Provincias::nombre($order->province),
                'city' => $order->city,
                'created_at' => ZonaHoraria::formatear($order->created_at),
            ]);

        $props = [
            'orders' => $orders,
            'filters' => [
                'search' => $search ?? '',
                'estado' => $estadoFilter,
            ],
        ];

        if ($request->user()->isAdmin()) {
            [$primerDia, $monthMeta] = $this->resolveMonth($request->get('month'));
            // Rango del mes argentino, ya en la zona en la que se guardan las fechas.
            [$desde, $hasta] = ZonaHoraria::rangoDelMes($primerDia);

            $props['stats'] = $this->buildMonthStats($desde, $hasta, $resumen);
            $props['dailyBreakdown'] = $this->buildDailyBreakdown($primerDia, $desde, $hasta);
            $props['month'] = $monthMeta;
        }

        return Inertia::render('Admin/Orders/Index', $props);
    }

    /**
     * Display the specified order.
     */
    public function show(Order $order): Response
    {
        $order->load('items.product.images');

        $transicionesDisponibles = collect(EstadoOrden::cases())
            ->filter(fn (EstadoOrden $destino) => $order->puedeTransicionarA($destino))
            ->map(fn (EstadoOrden $destino) => $destino->value)
            ->values();

        // Forma de pago con tarjeta elegida por el cliente en el checkout. Se
        // lee del snapshot de la orden (no del CardPaymentPlan, que pudo
        // borrarse): payment_plan_name null ⇒ pedido en efectivo/transferencia,
        // no se muestra nada. El `installment_amount` no se guarda en columna
        // porque es total_with_surcharge / installments — se deriva acá con el
        // mismo criterio que CardSurchargeService.
        $paymentPlan = null;

        if ($order->payment_plan_name !== null) {
            $installments = max(1, (int) $order->payment_plan_installments);
            $totalWithSurcharge = (float) $order->total_with_surcharge;
            $installmentAmount = round($totalWithSurcharge / $installments, 2);

            $paymentPlan = [
                'name' => $order->payment_plan_name,
                'installments' => (int) $order->payment_plan_installments,
                'surcharge_percentage' => (float) $order->surcharge_percentage,
                'surcharge_amount' => (float) $order->surcharge_amount,
                'total_with_surcharge' => $totalWithSurcharge,
                'installment_amount' => $installmentAmount,
                'formatted_surcharge_amount' => '$'.number_format((float) $order->surcharge_amount, 0, ',', '.'),
                'formatted_total_with_surcharge' => '$'.number_format($totalWithSurcharge, 0, ',', '.'),
                'formatted_installment_amount' => '$'.number_format($installmentAmount, 0, ',', '.'),
            ];
        }

        return Inertia::render('Admin/Orders/Show', [
            'provinces' => Provincias::all(),
            'order' => [
                'id' => $order->id,
                'name' => $order->name,
                'lastname' => $order->lastname,
                // «Nombre y apellido» en un solo campo (los pedidos anteriores al checkout mínimo lo traen partido: se junta).
                'nombre_completo' => $order->nombreCompleto(),
                'tipo_entrega' => ($order->tipo_entrega ?? TipoEntrega::Envio)->value,
                'tipo_entrega_label' => ($order->tipo_entrega ?? TipoEntrega::Envio)->label(),
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
                // Reserva en el Sistema Central (10.3): null = pedido de la web sola (stock local).
                'rukoz_ref' => $order->rukoz_ref,
                'rukoz_estado' => $order->rukoz_estado,
                'motivo_cancelacion' => $order->motivo_cancelacion,
                // Venta en el Sistema Central (10.4): `venta` es null mientras el pedido no se confirmó. Desde ahí es solo lectura.
                'puede_confirmar' => $order->puedeConfirmarse(),
                'esta_confirmado' => $order->estaConfirmado(),
                'venta' => $order->estaConfirmado() ? $this->ventaDelCentral($order) : null,
                'subtotal' => (float) $order->subtotal,
                'formatted_subtotal' => '$' . number_format((float) $order->subtotal, 0, ',', '.'),
                'discount_code' => $order->discount_code,
                'discount_amount' => (float) $order->discount_amount,
                'formatted_discount_amount' => '$' . number_format((float) $order->discount_amount, 0, ',', '.'),
                'total' => (float) $order->total,
                'formatted_total' => '$' . number_format((float) $order->total, 0, ',', '.'),
                // Forma de pago con tarjeta (snapshot) o null si fue en efectivo /
                // transferencia. El admin/vendedor lee de acá el monto exacto para
                // generar el link de pago en Mercado Pago a mano.
                'payment_plan' => $paymentPlan,
                'mensaje_whatsapp' => $order->mensaje_whatsapp,
                'created_at' => ZonaHoraria::formatear($order->created_at),
                'free_shipping' => (bool) $order->free_shipping,
                // Tarjeta «Datos del cliente»: se puede editar mientras el pedido no esté confirmado, cancelado ni anulado (null = sí).
                'datos_cliente_solo_lectura' => DatosDelCliente::motivoDeSoloLectura($order),
                'datos_requeridos' => DatosDeEnvio::REQUERIDOS,
                'items' => $order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'combo_id' => $item->combo_id,
                    'is_combo' => $item->combo_id !== null,
                    'product_title' => $item->product_title,
                    'cantidad' => $item->cantidad,
                    'precio_unitario' => (float) $item->precio_unitario,
                    'subtotal' => (float) $item->subtotal,
                    'primary_image' => $item->product?->primaryImage()?->path,
                    'product_active' => (bool) ($item->product?->is_active ?? false),
                    // Opciones elegidas (snapshot en el order_item), para que
                    // quien despacha sepa qué preparar. Con `custom_color_text`
                    // la variante era "a elección del cliente"; si no, `variant_name`
                    // es el color fijo. Null / [] cuando el ítem no tenía opciones.
                    'variant_name' => $item->variant_name,
                    'variant_color_hex' => $item->variant_color_hex,
                    'custom_color_text' => $item->custom_color_text,
                    'addons_selected' => $item->addons_selected ?? [],
                    // Componentes del combo (snapshot), para que quien despacha
                    // sepa qué productos y colores preparar.
                    'combo_selections' => $item->combo_selections ?? [],
                ]),
                'transiciones_disponibles' => $transicionesDisponibles,
            ],
        ]);
    }

    /**
     * Actualizar el estado de una orden.
     */
    public function updateStatus(Request $request, Order $order, StockService $stockService, DiscountCodeService $discountCodeService, ReservaDePedido $reservaDePedido): RedirectResponse
    {
        $validated = $request->validate([
            'estado' => ['required', 'string', Rule::enum(EstadoOrden::class)],
        ]);

        $nuevoEstado = EstadoOrden::from($validated['estado']);

        if (! $order->puedeTransicionarA($nuevoEstado)) {
            return back()->withErrors([
                'estado' => $this->mensajeTransicionInvalida($order, $nuevoEstado),
            ]);
        }

        // Pedido con el stock reservado en el Central (10.3): cancelar es liberar la reserva ALLÁ (HTTP, sin transacción
        // abierta) y después cancelar el pedido local liberando el cupón. No se repone stock local: nunca se descontó. Si el
        // Central no responde, el pedido queda cancelado con `rukoz_estado = cancelacion_pendiente` y la cancelación queda
        // en las compensaciones del paquete. Con INTEGRACION_CONFIRMACION apagada, despachar y volver a pendiente no toca stock
        // ni llama al Central; con ella encendida "Confirmar venta" reemplaza a "Despachado", y un pedido ya confirmado es de solo
        // lectura (Order::puedeTransicionarA).
        if ($nuevoEstado === EstadoOrden::Cancelado && $order->usaCentral()) {
            $reservaDePedido->cancelar($order, 'Cancelado desde el panel de Chispas Frías');

            return back()->with('success', "Orden actualizada a \"{$nuevoEstado->value}\".");
        }

        // La única transición que repone stock es pendiente -> cancelado. El chequeo se
        // hace acá, antes del update(), porque este último modifica $order->estado en
        // memoria.
        $esCancelacionDesdePendiente = $order->estado === EstadoOrden::Pendiente
            && $nuevoEstado === EstadoOrden::Cancelado;

        try {
            DB::transaction(function () use ($order, $nuevoEstado, $esCancelacionDesdePendiente, $stockService, $discountCodeService) {
                $order->update(['estado' => $nuevoEstado]);

                if ($esCancelacionDesdePendiente) {
                    $stockService->reponer($order);

                    // Idempotente: una segunda cancelación (o un reintento) no vuelve a
                    // devolver el uso del código.
                    $discountCodeService->liberarUso($order);
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
     * Guarda los datos del cliente desde la tarjeta «Datos del cliente» de la ficha (nombre, teléfono, DNI, provincia, localidad,
     * código postal, email). No toca las líneas; con reserva en el Central hace el PUT con los ítems actuales.
     */
    public function updateCliente(Request $request, Order $order, DatosDelCliente $datosDelCliente): RedirectResponse
    {
        $validated = $request->validate(DatosDeEnvio::reglas());

        $datosDelCliente->actualizar($order, Arr::only($validated, DatosDeEnvio::EDITABLES));

        return back()->with('success', 'Datos del cliente guardados.');
    }

    /**
     * La venta tal como la informa el Sistema Central (10.4), para la ficha del pedido: número, estado, pagado y saldo,
     * retención del despacho y seguimiento del envío. Todo sale de columnas locales (sin llamar al Central).
     *
     * @return array<string, mixed>
     */
    private function ventaDelCentral(Order $order): array
    {
        $dinero = fn ($valor) => $valor === null ? null : (float) $valor;
        $formato = fn ($valor) => $valor === null ? null : '$' . number_format((float) $valor, 0, ',', '.');

        $seguimiento = $order->rukoz_transportista !== null
            || $order->rukoz_numero_seguimiento !== null
            || $order->rukoz_url_seguimiento !== null;

        return [
            'numero' => $order->rukoz_numero_venta,
            'estado_central' => $order->rukoz_estado,
            'total' => $dinero($order->rukoz_total_venta),
            'formatted_total' => $formato($order->rukoz_total_venta),
            'pagado' => $dinero($order->rukoz_pagado),
            'formatted_pagado' => $formato($order->rukoz_pagado),
            'saldo' => $dinero($order->rukoz_saldo),
            'formatted_saldo' => $formato($order->rukoz_saldo),
            'envio_cobrado' => $dinero($order->envio_costo_cobrado),
            'formatted_envio_cobrado' => $formato($order->envio_costo_cobrado),
            // Se confirmó con «Despachar solo con pago completo» y todavía queda saldo.
            'retenido_por_pago' => (bool) $order->rukoz_retenido_por_pago,
            'seguimiento' => $seguimiento ? [
                'transportista' => $order->rukoz_transportista,
                'numero' => $order->rukoz_numero_seguimiento,
                'url' => self::urlSegura($order->rukoz_url_seguimiento),
            ] : null,
            'confirmado_at' => ZonaHoraria::formatear($order->confirmado_at),
            'empaquetado_at' => ZonaHoraria::formatear($order->rukoz_empaquetado_at),
            'despachado_at' => ZonaHoraria::formatear($order->despachado_at),
            'entregado_at' => ZonaHoraria::formatear($order->rukoz_retirado_at),
            'anulado_at' => ZonaHoraria::formatear($order->rukoz_anulado_at),
        ];
    }

    /** La URL de seguimiento viene del Central: solo se enlaza si es http(s). */
    public static function urlSegura(?string $url): ?string
    {
        $url = trim((string) $url);

        return preg_match('#^https?://#i', $url) === 1 ? $url : null;
    }

    /**
     * Mensaje de error específico según la transición rechazada.
     */
    private function mensajeTransicionInvalida(Order $order, EstadoOrden $destino): string
    {
        $actual = $order->estado;

        // Una venta confirmada en el Central (o más allá, o anulada) no se toca desde la web: ni cancelar ni cambiar de estado.
        if ($order->estaConfirmado() || $actual->esDelCentral()) {
            return 'Para anular, hacelo en el Sistema Central.';
        }

        if ($order->usaCentral() && $actual === EstadoOrden::Pendiente && $destino === EstadoOrden::Despachado) {
            return 'Este pedido se cierra con «Confirmar venta»: la venta se crea en el Sistema Central.';
        }

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
     * fechas y metadata de navegación. El mes es un mes ARGENTINO (ver
     * ZonaHoraria): un pedido hecho el 31 a las 22:00 en Buenos Aires pertenece
     * a ese mes aunque en UTC ya sea el 1 del siguiente. Nunca deja avanzar a un
     * mes futuro: si piden uno posterior al actual, se clampa al actual.
     *
     * @return array{0: Carbon, 1: array} Primer instante del mes (en hora argentina) y metadata.
     */
    private function resolveMonth(?string $monthParam): array
    {
        $current = ZonaHoraria::ahora()->startOfMonth();
        $requested = $current->copy();

        if ($monthParam) {
            try {
                // El "!" pone en cero lo que el formato no trae (día, hora): sin él, pedir
                // "2026-02" un día 31 desbordaría a marzo.
                $requested = Carbon::createFromFormat('!Y-m', $monthParam, ZonaHoraria::nombre())->startOfMonth();
            } catch (\Throwable $e) {
                $requested = $current->copy();
            }
        }

        if ($requested->gt($current)) {
            $requested = $current->copy();
        }

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

        return [$requested, $meta];
    }

    /**
     * Métricas de negocio del mes, separadas por lo que significa cada estado
     * (ver ResumenDePedidos y EstadoOrden::deVenta()):
     *
     *  - ventas: pedidos con `rukoz_ref` confirmados en el Central (por `confirmado_at`) y pedidos de la web sola despachados
     *    — `revenue` y `avg_order_value` salen SOLO de acá;
     *  - pendientes: cantidad y monto aparte, nunca mezclados con las ventas;
     *  - cancelados: se cuentan, jamás suman monto.
     *
     * `top_product` es de lo vendido (ventas); `top_locations` cuenta pedidos
     * solicitados (todo lo no cancelado), proxy de "tipo de envío" ya que el
     * checkout no distingue métodos de envío.
     *
     * $desde/$hasta ya están en la zona de la base (ZonaHoraria::rangoDelMes).
     */
    private function buildMonthStats(Carbon $desde, Carbon $hasta, ResumenDePedidos $resumen): array
    {
        $totales = $resumen->delRango($desde, $hasta);

        $avgOrderValue = $totales['ventas_cantidad'] > 0
            ? $totales['ventas_monto'] / $totales['ventas_cantidad']
            : 0;

        // Lo vendido: las ventas del rango por su fecha de venta (confirmado_at para los pedidos con rukoz_ref).
        $topProduct = OrderItem::query()
            ->whereIn('order_items.order_id', Order::ventasEntre($desde, $hasta)->select('orders.id'))
            ->select('order_items.product_title')
            ->selectRaw('SUM(order_items.cantidad) as total_qty')
            ->groupBy('order_items.product_title')
            ->orderByDesc('total_qty')
            ->first();

        $topLocations = Order::whereBetween('created_at', [$desde, $hasta])
            ->whereNotIn('estado', [EstadoOrden::Cancelado->value, EstadoOrden::Anulado->value])
            ->select('province')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('province')
            ->orderByDesc('total')
            ->limit(3)
            ->get()
            ->map(fn ($row) => [
                // Los retiros en oficina no tienen provincia: se agrupan bajo la oficina.
                'province' => Provincias::nombre($row->province) ?? TipoEntrega::OFICINA,
                'count' => (int) $row->total,
            ])
            ->values();

        return [
            // Todo lo recibido en el mes, cancelados incluidos.
            'orders_count' => $totales['pedidos'],
            'sales_count' => $totales['ventas_cantidad'],
            'pending_count' => $totales['pendientes_cantidad'],
            'pending_amount' => $totales['pendientes_monto'],
            'formatted_pending_amount' => '$' . number_format($totales['pendientes_monto'], 0, ',', '.'),
            'cancelled_count' => $totales['cancelados_cantidad'],
            // Ingresos = ventas cerradas (Order::ventas). No incluye pendientes, cancelados ni anulados.
            'revenue' => $totales['ventas_monto'],
            'formatted_revenue' => '$' . number_format($totales['ventas_monto'], 0, ',', '.'),
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
     * "pedidos por día". Los días son días ARGENTINOS (no se agrupa con
     * DATE(created_at), que cortaría el día a las 21:00 de Buenos Aires).
     * orders_count cuenta todos los pedidos recibidos ese día (cualquier
     * estado) y pending_amount los pendientes, por el día en que se crearon;
     * revenue y sales_count solo las ventas, por el día de su FECHA DE VENTA
     * (`confirmado_at` para los pedidos con `rukoz_ref`, la creación para los de
     * siempre). Mismo criterio que buildMonthStats().
     *
     * @param  Carbon  $primerDia  Primer día del mes, en hora argentina.
     */
    private function buildDailyBreakdown(Carbon $primerDia, Carbon $desde, Carbon $hasta): array
    {
        $pendientes = array_map(fn (EstadoOrden $estado) => $estado->value, EstadoOrden::pendientes());

        $porDia = Order::whereBetween('created_at', [$desde, $hasta])
            ->toBase()
            ->get(['created_at', 'estado', 'total'])
            ->groupBy(fn ($row) => ZonaHoraria::aLocal(Carbon::parse($row->created_at, config('app.timezone')))->toDateString());

        $ventasPorDia = Order::ventasEntre($desde, $hasta)
            ->get(['id', 'created_at', 'confirmado_at', 'rukoz_ref', 'total'])
            ->groupBy(fn (Order $venta) => ZonaHoraria::aLocal($venta->fechaDeVenta())->toDateString());

        $days = collect();
        $cursor = $primerDia->copy()->startOfDay();
        $ultimo = $primerDia->copy()->endOfMonth();

        while ($cursor->lte($ultimo)) {
            $key = $cursor->toDateString();
            $filas = $porDia->get($key, collect());
            $ventas = $ventasPorDia->get($key, collect());
            $revenue = (float) $ventas->sum('total');
            $pendingAmount = (float) $filas->whereIn('estado', $pendientes)->sum('total');

            $days->push([
                'day' => $cursor->day,
                'date' => $key,
                'orders_count' => $filas->count(),
                'sales_count' => $ventas->count(),
                'revenue' => $revenue,
                'formatted_revenue' => '$' . number_format($revenue, 0, ',', '.'),
                'pending_amount' => $pendingAmount,
                'formatted_pending_amount' => '$' . number_format($pendingAmount, 0, ',', '.'),
            ]);

            $cursor->addDay();
        }

        return $days->values()->all();
    }
}
