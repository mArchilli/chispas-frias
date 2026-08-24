<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DiscountCode;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DiscountCodeController extends Controller
{
    /**
     * Listado de códigos con filtro por estado calculado y búsqueda por código.
     */
    public function index(Request $request): Response
    {
        $search = $request->get('search');
        $status = $request->get('status');
        $now = now();

        $query = DiscountCode::query();

        if ($search) {
            $query->where('code', 'like', '%' . strtoupper(trim($search)) . '%');
        }

        if ($status) {
            match ($status) {
                'activo' => $query->active()->where(function ($q) {
                    $q->whereNull('usage_limit')->orWhereColumn('usage_count', '<', 'usage_limit');
                }),
                'programado' => $query->where('is_active', true)->where('start_date', '>', $now),
                'expirado' => $query->where('is_active', true)->whereNotNull('end_date')->where('end_date', '<', $now),
                'inactivo' => $query->where('is_active', false),
                'agotado' => $query->whereNotNull('usage_limit')->whereColumn('usage_count', '>=', 'usage_limit'),
                default => null,
            };
        }

        $discountCodes = $query->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (DiscountCode $discountCode) => $this->transform($discountCode, $now));

        return Inertia::render('Admin/DiscountCodes/Index', [
            'discountCodes' => $discountCodes,
            'filters' => [
                'search' => $search ?? '',
                'status' => $status ?? '',
            ],
        ]);
    }

    /**
     * Formulario de alta.
     */
    public function create(): Response
    {
        return Inertia::render('Admin/DiscountCodes/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:discount_codes,code',
            'description' => 'nullable|string|max:1000',
            'percentage' => 'required|numeric|min:0.01|max:100',
            'min_purchase_amount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'boolean',
        ]);

        DiscountCode::create($validated);

        return redirect()->route('admin.discount-codes.index')->with('success', 'Código de descuento creado exitosamente');
    }

    /**
     * Formulario de edición.
     */
    public function edit(DiscountCode $discountCode): Response
    {
        return Inertia::render('Admin/DiscountCodes/Edit', [
            'discountCode' => [
                'id' => $discountCode->id,
                'code' => $discountCode->code,
                'description' => $discountCode->description,
                'percentage' => $discountCode->percentage,
                'min_purchase_amount' => $discountCode->min_purchase_amount,
                'usage_limit' => $discountCode->usage_limit,
                'usage_count' => $discountCode->usage_count,
                'start_date' => $discountCode->start_date,
                'end_date' => $discountCode->end_date,
                'is_active' => $discountCode->is_active,
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * `code` sólo se puede modificar mientras el código no tenga usos: una vez
     * usado en al menos una orden, renombrarlo confundiría el historial (el
     * cliente que lo usó vio un texto que dejaría de existir) sin ganar nada
     * frente a la alternativa de desactivarlo y crear uno nuevo.
     */
    public function update(Request $request, DiscountCode $discountCode): RedirectResponse
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:discount_codes,code,' . $discountCode->id,
            'description' => 'nullable|string|max:1000',
            'percentage' => 'required|numeric|min:0.01|max:100',
            'min_purchase_amount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'boolean',
        ]);

        if ($discountCode->usage_count > 0 && $validated['code'] !== $discountCode->code) {
            return back()
                ->withErrors(['code' => 'No se puede modificar el código una vez que fue usado en alguna orden. Desactivalo y creá uno nuevo si necesitás otro texto.'])
                ->withInput();
        }

        $discountCode->update($validated);

        return redirect()->route('admin.discount-codes.index')->with('success', 'Código de descuento actualizado exitosamente');
    }

    /**
     * Remove the specified resource from storage.
     *
     * Sólo se permite borrar códigos sin usos: `orders.discount_code_id` ya
     * apunta a null on delete, pero borrar un código que se usó le quita
     * contexto a esas órdenes (aunque `orders.discount_code` conserva el
     * snapshot del texto). Desactivar es la vía recomendada para dejar de
     * ofrecerlo sin perder trazabilidad.
     */
    public function destroy(DiscountCode $discountCode): RedirectResponse
    {
        if ($discountCode->usage_count > 0) {
            return back()->withErrors([
                'error' => 'Este código ya fue usado en órdenes: no se puede eliminar. Desactivalo en su lugar.',
            ]);
        }

        $discountCode->delete();

        return redirect()->route('admin.discount-codes.index')->with('success', 'Código de descuento eliminado exitosamente');
    }

    /**
     * Toggle discount code status
     */
    public function toggleStatus(DiscountCode $discountCode): RedirectResponse
    {
        $discountCode->update(['is_active' => ! $discountCode->is_active]);

        $status = $discountCode->is_active ? 'activado' : 'desactivado';
        return back()->with('success', "Código {$status} exitosamente");
    }

    /**
     * Estado visual de un código: Inactivo pisa a los demás (igual que en
     * ofertas), después Agotado (llegó a usage_limit, sea cual sea la fecha),
     * después Programado/Expirado por fecha, y por defecto Activo.
     */
    private function resolveStatus(DiscountCode $discountCode, Carbon $now): string
    {
        if (! $discountCode->is_active) {
            return 'inactivo';
        }
        if ($discountCode->agotado()) {
            return 'agotado';
        }
        if ($discountCode->start_date && $discountCode->start_date->gt($now)) {
            return 'programado';
        }
        if ($discountCode->end_date && $discountCode->end_date->lt($now)) {
            return 'expirado';
        }
        return 'activo';
    }

    private function transform(DiscountCode $discountCode, Carbon $now): array
    {
        return [
            'id' => $discountCode->id,
            'code' => $discountCode->code,
            'description' => $discountCode->description,
            'percentage' => $discountCode->percentage,
            'min_purchase_amount' => $discountCode->min_purchase_amount,
            'usage_limit' => $discountCode->usage_limit,
            'usage_count' => $discountCode->usage_count,
            'start_date' => $discountCode->start_date,
            'end_date' => $discountCode->end_date,
            'is_active' => $discountCode->is_active,
            'status' => $this->resolveStatus($discountCode, $now),
        ];
    }
}
