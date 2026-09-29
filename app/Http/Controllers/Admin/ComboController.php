<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\StoresPublicUploads;
use App\Http\Controllers\Controller;
use App\Models\Combo;
use App\Models\ComboImage;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ComboController extends Controller
{
    use StoresPublicUploads;

    /**
     * Listado de combos para el panel.
     */
    public function index(Request $request): Response
    {
        $search = $request->get('search');

        $query = Combo::with(['images'])->withCount('items');

        if ($search) {
            $query->where('title', 'like', "%{$search}%");
        }

        $combos = $query->orderByDesc('created_at')
            ->paginate(15)
            ->through(fn (Combo $combo) => [
                'id' => $combo->id,
                'title' => $combo->title,
                'price' => (float) $combo->price,
                'formatted_price' => $combo->formatted_price,
                'is_free_shipping' => $combo->is_free_shipping,
                'is_active' => $combo->is_active,
                'items_count' => $combo->items_count,
                'primary_image' => $combo->primaryImage()?->url,
                'created_at' => $combo->created_at->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Admin/Combos/Index', [
            'combos' => $combos,
            'filters' => ['search' => $search ?? ''],
        ]);
    }

    /**
     * Formulario de alta.
     */
    public function create(): Response
    {
        return Inertia::render('Admin/Combos/Create', [
            'products' => $this->productsCatalog(),
        ]);
    }

    /**
     * Catálogo liviano de productos activos para el selector de componentes:
     * id, título, precio de referencia, imagen principal y si tiene variantes
     * de color (para avisar en el front que el cliente elegirá color).
     */
    private function productsCatalog()
    {
        return Product::query()
            ->where('is_active', true)
            ->with(['images'])
            ->withCount(['variants as active_variants_count' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('title')
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'title' => $product->title,
                'price' => (float) $product->price,
                'primary_image' => $product->primaryImage()?->url,
                'has_variants' => $product->active_variants_count > 0,
            ])
            ->values();
    }

    /**
     * Alta de combo.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateCombo($request);

        $combo = DB::transaction(function () use ($validated, $request) {
            $combo = Combo::create([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'price' => $validated['price'],
                'is_free_shipping' => $request->input('is_free_shipping', '0') === '1',
                'is_active' => $request->input('is_active', '1') === '1',
            ]);

            $this->syncItems($combo, $validated['items']);

            return $combo;
        });

        if ($request->hasFile('images')) {
            $this->storeImages($combo, $request->file('images'), startPrimary: true);
        }

        return redirect()
            ->route('admin.combos.index')
            ->with('success', 'Combo creado exitosamente.');
    }

    /**
     * Formulario de edición.
     */
    public function edit(Combo $combo): Response
    {
        $combo->load(['items.product', 'images']);

        return Inertia::render('Admin/Combos/Edit', [
            'products' => $this->productsCatalog(),
            'combo' => [
                'id' => $combo->id,
                'title' => $combo->title,
                'description' => $combo->description,
                'price' => (float) $combo->price,
                'is_free_shipping' => $combo->is_free_shipping,
                'is_active' => $combo->is_active,
                'items' => $combo->items->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    // Título de referencia por si el producto se desactivó luego.
                    'product_title' => $item->product?->title,
                ])->values(),
                'images' => $combo->images->map(fn (ComboImage $image) => [
                    'id' => $image->id,
                    'url' => $image->url,
                    'is_primary' => $image->is_primary,
                    'type' => $image->type,
                ])->values(),
            ],
        ]);
    }

    /**
     * Actualización de combo.
     */
    public function update(Request $request, Combo $combo): RedirectResponse
    {
        $validated = $this->validateCombo($request);

        DB::transaction(function () use ($validated, $request, $combo) {
            $combo->update([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'price' => $validated['price'],
                'is_free_shipping' => $request->input('is_free_shipping', '0') === '1',
                'is_active' => $request->input('is_active', '1') === '1',
            ]);

            $this->syncItems($combo, $validated['items']);
        });

        // Borrar imágenes marcadas.
        if ($request->filled('remove_images')) {
            $this->removeImages($combo, (array) $request->input('remove_images'));
        }

        // Agregar imágenes nuevas.
        if ($request->hasFile('new_images')) {
            $startPrimary = $combo->images()->count() === 0;
            $this->storeImages($combo, $request->file('new_images'), startPrimary: $startPrimary);
        }

        return redirect()
            ->route('admin.combos.index')
            ->with('success', 'Combo actualizado exitosamente.');
    }

    /**
     * Baja de combo (borra también sus imágenes físicas).
     */
    public function destroy(Combo $combo): RedirectResponse
    {
        $imagesDir = $this->comboImagesDir();

        foreach ($combo->images as $image) {
            $this->borrarArchivoPublic($imagesDir, $image->path);
        }

        $combo->delete();

        return redirect()
            ->route('admin.combos.index')
            ->with('success', 'Combo eliminado exitosamente.');
    }

    /**
     * Activar / desactivar un combo.
     */
    public function toggleStatus(Combo $combo): RedirectResponse
    {
        $combo->update(['is_active' => ! $combo->is_active]);

        $status = $combo->is_active ? 'activado' : 'desactivado';

        return back()->with('success', "Combo {$status} exitosamente.");
    }

    /**
     * Marcar una imagen del combo como principal.
     */
    public function setPrimaryImage(Combo $combo, ComboImage $image): RedirectResponse
    {
        if ($image->combo_id !== $combo->id) {
            return back()->withErrors(['image' => 'La imagen no pertenece a este combo.']);
        }

        $image->setPrimary();

        return back()->with('success', 'Imagen principal actualizada exitosamente.');
    }

    /**
     * Reglas comunes de validación (alta y edición). Los booleanos vienen como
     * '1'/'0' por FormData y se resuelven aparte con input().
     *
     * @return array{title: string, description: string|null, price: float, items: array<int, array{product_id: int, quantity: int}>}
     */
    private function validateCombo(Request $request): array
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0|max:99999999.99',
            'is_free_shipping' => 'nullable',
            'is_active' => 'nullable',
            'items' => 'required|array|min:1|max:50',
            'items.*.product_id' => 'required|integer|distinct|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1|max:999',
            'images' => 'nullable|array|max:10',
            'images.*' => 'file|mimes:jpeg,png,jpg,gif,webp,mp4,mov,avi,wmv,flv,webm|max:20480',
            'new_images' => 'nullable|array|max:10',
            'new_images.*' => 'file|mimes:jpeg,png,jpg,gif,webp,mp4,mov,avi,wmv,flv,webm|max:20480',
            'remove_images' => 'nullable|array',
            'remove_images.*' => 'integer',
        ]);

        return [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'items' => array_values($validated['items']),
        ];
    }

    /**
     * Full-sync de los productos del combo, mismo criterio que
     * ProductController::syncPriceTiers: borra los que no vienen, crea/actualiza
     * el resto, con el sort_order dado por el orden del array.
     *
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     */
    private function syncItems(Combo $combo, array $items): void
    {
        $incomingProductIds = collect($items)->pluck('product_id')->all();

        $combo->items()->whereNotIn('product_id', $incomingProductIds)->delete();

        foreach (array_values($items) as $i => $item) {
            $combo->items()->updateOrCreate(
                ['product_id' => (int) $item['product_id']],
                [
                    'quantity' => (int) $item['quantity'],
                    'sort_order' => $i,
                ]
            );
        }
    }

    /**
     * Guarda los archivos subidos como imágenes del combo, con el mismo
     * mecanismo tolerante que los productos (StoresPublicUploads). La primera
     * imagen queda como principal cuando $startPrimary es true y todavía no hay
     * ninguna marcada.
     *
     * @param  array<int, \Illuminate\Http\UploadedFile>  $files
     */
    private function storeImages(Combo $combo, array $files, bool $startPrimary): void
    {
        $dir = $this->comboImagesDir();
        $existing = $combo->images()->count();

        foreach (array_values($files) as $index => $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }

            try {
                $mimeType = $file->getMimeType() ?: 'application/octet-stream';
            } catch (\Throwable) {
                $mimeType = 'application/octet-stream';
            }

            $type = str_contains($mimeType, 'video') ? 'video' : 'image';
            $fileName = time().'_'.($existing + $index).'_'.uniqid().'.'.$file->getClientOriginalExtension();

            if (! $this->moverArchivoSubidoAPublic($file, $dir, $fileName)) {
                continue;
            }

            $combo->images()->create([
                'path' => $fileName,
                'alt_text' => $combo->title,
                'sort_order' => $existing + $index + 1,
                'is_primary' => $startPrimary && $existing === 0 && $index === 0,
                'type' => $type,
                'mime_type' => $mimeType,
            ]);
        }
    }

    /**
     * Borra las imágenes indicadas (por id) del combo, archivo físico incluido.
     * Si se borró la principal, promueve la primera imagen restante.
     *
     * @param  array<int, int|string>  $imageIds
     */
    private function removeImages(Combo $combo, array $imageIds): void
    {
        $ids = array_map('intval', $imageIds);
        $dir = $this->comboImagesDir();

        $images = $combo->images()->whereIn('id', $ids)->get();
        $borroPrincipal = $images->contains(fn (ComboImage $img) => $img->is_primary);

        foreach ($images as $image) {
            $this->borrarArchivoPublic($dir, $image->path);
            $image->delete();
        }

        if ($borroPrincipal) {
            $primera = $combo->images()->orderBy('sort_order')->first();
            $primera?->update(['is_primary' => true]);
        }
    }

    /**
     * Carpeta física donde viven las imágenes (la misma que las de productos).
     */
    private function comboImagesDir(): string
    {
        $relative = ltrim(env('PRODUCT_IMAGES_PATH', '/images/products/'), '/');

        return public_path($relative);
    }
}
