<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoOrden;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    /**
     * Display a listing of orders.
     */
    public function index(Request $request): Response
    {
        $search = $request->get('search');
        $estadoFilter = $request->get('estado');

        $query = Order::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('lastname', 'like', "%{$search}%")
                  ->orWhere('dni', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($estadoFilter) {
            $query->where('estado', $estadoFilter);
        }

        $orders = $query->orderByDesc('created_at')
            ->paginate(15)
            ->through(function (Order $order) {
                return [
                    'id' => $order->id,
                    'name' => $order->name,
                    'lastname' => $order->lastname,
                    'estado' => $order->estado->value,
                    'total' => (float) $order->total,
                    'formatted_total' => '$' . number_format((float) $order->total, 0, ',', '.'),
                    'created_at' => $order->created_at->format('d/m/Y H:i'),
                ];
            });

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders,
            'filters' => [
                'search' => $search ?? '',
                'estado' => $estadoFilter ?? '',
            ],
        ]);
    }

    /**
     * Display the specified order.
     */
    public function show(Order $order): Response
    {
        $order->load('items');

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
                    'product_title' => $item->product_title,
                    'cantidad' => $item->cantidad,
                    'precio_unitario' => (float) $item->precio_unitario,
                    'subtotal' => (float) $item->subtotal,
                ]),
                'transiciones_disponibles' => $transicionesDisponibles,
            ],
        ]);
    }

    /**
     * Actualizar el estado de una orden.
     */
    public function updateStatus(Request $request, Order $order): RedirectResponse
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

        $order->update(['estado' => $nuevoEstado]);

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
}
