<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CardPaymentPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ABM del catálogo de planes de pago con tarjeta de crédito. Todo bajo el Gate
 * 'gestionar-configuracion' (mismo que Settings): es configuración del negocio.
 *
 * El recargo que definen estos planes es 100% informativo — no se integra
 * ningún SDK/API/webhook de Mercado Pago. Sirve para que el vendedor sepa por
 * qué monto generar el link de pago manual desde su propia cuenta.
 */
class CardPaymentPlanController extends Controller
{
    /**
     * Listado del catálogo, ordenado por sort_order. Marca `en_uso` para
     * bloquear el borrado de planes ya usados en alguna orden.
     */
    public function index(): Response
    {
        $plans = CardPaymentPlan::query()
            ->withCount('orders')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (CardPaymentPlan $plan) => $this->transform($plan));

        return Inertia::render('Admin/CardPaymentPlans/Index', [
            'plans' => $plans,
        ]);
    }

    /**
     * Formulario de alta.
     */
    public function create(): Response
    {
        return Inertia::render('Admin/CardPaymentPlans/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        CardPaymentPlan::create($this->validated($request));

        return redirect()->route('admin.card-payment-plans.index')
            ->with('success', 'Plan de cuotas creado exitosamente');
    }

    /**
     * Formulario de edición.
     */
    public function edit(CardPaymentPlan $cardPaymentPlan): Response
    {
        return Inertia::render('Admin/CardPaymentPlans/Edit', [
            'plan' => [
                'id' => $cardPaymentPlan->id,
                'name' => $cardPaymentPlan->name,
                'installments' => $cardPaymentPlan->installments,
                'surcharge_percentage' => $cardPaymentPlan->surcharge_percentage,
                'sort_order' => $cardPaymentPlan->sort_order,
                'is_active' => $cardPaymentPlan->is_active,
                'en_uso' => $cardPaymentPlan->orders()->exists(),
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, CardPaymentPlan $cardPaymentPlan): RedirectResponse
    {
        $cardPaymentPlan->update($this->validated($request));

        return redirect()->route('admin.card-payment-plans.index')
            ->with('success', 'Plan de cuotas actualizado exitosamente');
    }

    /**
     * Remove the specified resource from storage.
     *
     * Sólo se permite borrar planes que nunca se usaron en una orden: la orden
     * guarda un snapshot (payment_plan_name, surcharge_percentage, ...) pero
     * borrar el plan del catálogo le quita contexto a ese historial. Desactivar
     * es la vía recomendada para dejar de ofrecerlo. Mismo criterio que
     * AddonController::destroy / DiscountCodeController::destroy.
     */
    public function destroy(CardPaymentPlan $cardPaymentPlan): RedirectResponse
    {
        if ($cardPaymentPlan->orders()->exists()) {
            return back()->withErrors([
                'error' => 'Este plan ya fue usado en órdenes: no se puede eliminar. Desactivalo en su lugar.',
            ]);
        }

        $cardPaymentPlan->delete();

        return redirect()->route('admin.card-payment-plans.index')
            ->with('success', 'Plan de cuotas eliminado exitosamente');
    }

    /**
     * Toggle card payment plan status
     */
    public function toggleStatus(CardPaymentPlan $cardPaymentPlan): RedirectResponse
    {
        $cardPaymentPlan->update(['is_active' => ! $cardPaymentPlan->is_active]);

        $status = $cardPaymentPlan->is_active ? 'activado' : 'desactivado';

        return back()->with('success', "Plan {$status} exitosamente");
    }

    /**
     * Reglas de validación compartidas por store/update, con `sort_order` e
     * `is_active` normalizados a sus defaults cuando el form no los manda.
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'installments' => ['required', 'integer', 'min:1'],
            'surcharge_percentage' => ['required', 'numeric', 'min:0', 'max:999.99'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ], [
            'name.required' => 'El nombre del plan es obligatorio.',
            'installments.required' => 'La cantidad de cuotas es obligatoria.',
            'installments.min' => 'La cantidad de cuotas debe ser mayor a 0.',
            'surcharge_percentage.required' => 'El recargo es obligatorio.',
            'surcharge_percentage.numeric' => 'El recargo debe ser un número.',
            'surcharge_percentage.min' => 'El recargo no puede ser negativo.',
        ]);

        return [
            'name' => $validated['name'],
            'installments' => $validated['installments'],
            'surcharge_percentage' => $validated['surcharge_percentage'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ];
    }

    private function transform(CardPaymentPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'installments' => $plan->installments,
            'surcharge_percentage' => $plan->surcharge_percentage,
            'sort_order' => $plan->sort_order,
            'is_active' => $plan->is_active,
            'en_uso' => $plan->orders_count > 0,
        ];
    }
}
