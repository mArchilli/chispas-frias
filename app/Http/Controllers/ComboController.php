<?php

namespace App\Http\Controllers;

use App\Models\Combo;
use App\Models\Product;
use App\Models\ProductVariant;
use Inertia\Inertia;
use Inertia\Response;

class ComboController extends Controller
{
    /**
     * Ficha pública de un combo. Muestra el precio fijo, el envío gratis (si
     * corresponde) y, por cada producto que lo integra, el selector de color
     * (variantes activas del producto). El precio no depende de los componentes
     * ni de la variante elegida.
     */
    public function show(Combo $combo): Response
    {
        if (! $combo->is_active) {
            abort(404);
        }

        $combo->load([
            'items.product.variantsActive',
            'items.product.images',
            'images',
        ]);

        return Inertia::render('Combos/Show', [
            'combo' => [
                'id' => $combo->id,
                'title' => $combo->title,
                'description' => $combo->description,
                'price' => (float) $combo->price,
                'is_free_shipping' => $combo->is_free_shipping,
                'images' => $combo->images->map(fn ($image) => [
                    'id' => $image->id,
                    'url' => $image->url,
                    'alt_text' => $image->alt_text,
                    'is_primary' => $image->is_primary,
                    'type' => $image->type,
                    'mime_type' => $image->mime_type,
                ])->values(),
                'components' => $combo->items->map(fn ($item) => $this->mapComponent($item))->values(),
            ],
        ]);
    }

    /**
     * Serializa un componente del combo: producto + sus variantes activas (para
     * el selector de color). Mismo shape de variante que consume ProductOptions.
     */
    private function mapComponent($item): array
    {
        $product = $item->product;

        return [
            'product_id' => $product->id,
            'title' => $product->title,
            'quantity' => (int) $item->quantity,
            'primary_image' => $product->primaryImage()?->url,
            'variants' => $product->variantsActive->map(fn (ProductVariant $variant) => [
                'id' => $variant->id,
                'name' => $variant->name,
                'color_hex' => $variant->color_hex,
                'is_custom_color' => $variant->is_custom_color,
                // El recargo no aplica a los combos (precio fijo); se manda en 0
                // para que la UI reutilice el mismo shape sin mostrar recargo.
                'price_addon' => 0.0,
                'stock' => $variant->stock,
                'is_active' => $variant->is_active,
            ])->values(),
        ];
    }
}
