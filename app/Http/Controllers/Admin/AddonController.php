<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use App\Models\OrderItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AddonController extends Controller
{
    /**
     * Listado del catálogo global de add-ons, con filtro por estado y búsqueda
     * por nombre. Mismo estilo que DiscountCodeController.
     */
    public function index(Request $request): Response
    {
        $search = $request->get('search');
        $status = $request->get('status');

        $query = Addon::query()->withCount('products');

        if ($search) {
            $query->where('name', 'like', '%' . trim($search) . '%');
        }

        if ($status === 'activo') {
            $query->where('is_active', true);
        } elseif ($status === 'inactivo') {
            $query->where('is_active', false);
        }

        $usados = $this->addonIdsUsadosEnOrdenes();

        $addons = $query->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Addon $addon) => $this->transform($addon, $usados));

        return Inertia::render('Admin/Addons/Index', [
            'addons' => $addons,
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
        return Inertia::render('Admin/Addons/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        Addon::create($validated);

        return redirect()->route('admin.addons.index')->with('success', 'Add-on creado exitosamente');
    }

    /**
     * Formulario de edición.
     */
    public function edit(Addon $addon): Response
    {
        return Inertia::render('Admin/Addons/Edit', [
            'addon' => [
                'id' => $addon->id,
                'name' => $addon->name,
                'description' => $addon->description,
                'price' => $addon->price,
                'requires_text' => $addon->requires_text,
                'text_placeholder' => $addon->text_placeholder,
                'max_characters' => $addon->max_characters,
                'is_active' => $addon->is_active,
                'en_uso' => $addon->haSidoUsado(),
                'products_count' => $addon->products()->count(),
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Addon $addon): RedirectResponse
    {
        $validated = $this->validated($request, $addon);

        $addon->update($validated);

        return redirect()->route('admin.addons.index')->with('success', 'Add-on actualizado exitosamente');
    }

    /**
     * Remove the specified resource from storage.
     *
     * Sólo se permite borrar add-ons que nunca se usaron en una orden: los
     * order_items guardan un snapshot del add-on en addons_selected, pero borrar
     * el add-on del catálogo le quita contexto a ese historial. Desactivar es la
     * vía recomendada para dejar de ofrecerlo. Mismo criterio que
     * DiscountCodeController::destroy con usage_count.
     */
    public function destroy(Addon $addon): RedirectResponse
    {
        if ($addon->haSidoUsado()) {
            return back()->withErrors([
                'error' => 'Este add-on ya fue usado en órdenes: no se puede eliminar. Desactivalo en su lugar.',
            ]);
        }

        $addon->delete();

        return redirect()->route('admin.addons.index')->with('success', 'Add-on eliminado exitosamente');
    }

    /**
     * Toggle addon status
     */
    public function toggleStatus(Addon $addon): RedirectResponse
    {
        $addon->update(['is_active' => ! $addon->is_active]);

        $status = $addon->is_active ? 'activado' : 'desactivado';

        return back()->with('success', "Add-on {$status} exitosamente");
    }

    /**
     * Reglas de validación compartidas por store/update. `name` es único en el
     * catálogo global (ignorando el propio id al editar).
     */
    private function validated(Request $request, ?Addon $addon = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('addons', 'name')->ignore($addon?->id)],
            'description' => 'nullable|string|max:2000',
            'price' => 'required|numeric|min:0|max:99999999.99',
            'requires_text' => 'boolean',
            'text_placeholder' => 'nullable|string|max:255',
            'max_characters' => 'nullable|integer|min:1|max:2000',
            'is_active' => 'boolean',
        ]);
    }

    /**
     * Set de addon_id presentes en cualquier order_items.addons_selected, para
     * marcar `en_uso` en el listado sin una query por fila.
     *
     * @return Collection<int, int>
     */
    private function addonIdsUsadosEnOrdenes(): Collection
    {
        return OrderItem::query()
            ->whereNotNull('addons_selected')
            ->pluck('addons_selected')
            ->flatMap(fn ($seleccionados) => collect($seleccionados)->pluck('addon_id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * @param  Collection<int, int>  $usados
     */
    private function transform(Addon $addon, Collection $usados): array
    {
        return [
            'id' => $addon->id,
            'name' => $addon->name,
            'description' => $addon->description,
            'price' => $addon->price,
            'requires_text' => $addon->requires_text,
            'text_placeholder' => $addon->text_placeholder,
            'max_characters' => $addon->max_characters,
            'is_active' => $addon->is_active,
            'products_count' => $addon->products_count,
            'en_uso' => $usados->contains($addon->id),
        ];
    }
}
