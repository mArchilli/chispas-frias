<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Productos que se ofrecen en el carrito y en el drawer («Compatible con tu pedido»), según lo que el cliente ya lleva.
 *
 *  1. Compatibles: los productos que el admin marcó como compatibles con alguno del carrito (product_compatible, relación
 *     simétrica: una pistola sugiere su chispa fría y esa chispa sugiere la pistola). Si un producto es compatible con varios
 *     del carrito va primero; a igual cantidad, manda el orden que eligió el admin. Cada uno viaja con `compatible_con`: los
 *     títulos de lo del carrito con lo que sirve.
 *  2. «Sugerir siempre» (products.sugerir_en_carrito, p. ej. chispas frías genéricas), solo si NINGÚN producto del carrito tiene
 *     compatibilidad definida: si el cliente lleva algo con compatibilidad, una chispa genérica podría no servirle y no se mezcla.
 *
 * Nunca devuelve un producto que ya está en el carrito (suelto o dentro de un combo), inactivo o sin stock. No escribe nada.
 */
class SugerenciasDeCarrito
{
    public const LIMITE = 4;

    public function __construct(private readonly PricingService $pricingService) {}

    /**
     * @param  array<int, int>  $enCarrito  Ids de los productos del carrito (los de la receta de un combo incluidos).
     * @return Collection<int, array<string, mixed>>
     */
    public function para(array $enCarrito): Collection
    {
        $enCarrito = collect($enCarrito)->map(fn ($id) => (int) $id)->unique()->values();

        if ($enCarrito->isEmpty()) {
            return collect();
        }

        $pares = DB::table('product_compatible')
            ->where(fn ($q) => $q->whereIn('product_id', $enCarrito)->orWhereIn('compatible_id', $enCarrito))
            ->get(['product_id', 'compatible_id', 'sort_order']);

        // candidato => ids del carrito con los que es compatible, y el menor `sort_order` visto.
        $compatibilidad = [];

        foreach ($pares as $par) {
            foreach ([[$par->product_id, $par->compatible_id], [$par->compatible_id, $par->product_id]] as [$delCarrito, $candidato]) {
                if (! $enCarrito->contains($delCarrito) || $enCarrito->contains($candidato)) {
                    continue;
                }

                $compatibilidad[$candidato]['con'][$delCarrito] = true;
                $compatibilidad[$candidato]['orden'] = min($compatibilidad[$candidato]['orden'] ?? PHP_INT_MAX, $par->sort_order);
            }
        }

        $candidatos = fn () => Product::with(['images', 'currentOffer', 'priceTiers'])
            ->withCount(['variantsActive as variantes_activas_count'])
            ->active()
            ->inStock()
            ->whereNotIn('id', $enCarrito);

        $elegidos = collect();

        if ($compatibilidad !== []) {
            $titulosCarrito = Product::whereIn('id', $enCarrito)->pluck('title', 'id');

            $elegidos = $candidatos()->whereIn('id', array_keys($compatibilidad))->get()
                ->sortBy([
                    fn (Product $a, Product $b) => count($compatibilidad[$b->id]['con']) <=> count($compatibilidad[$a->id]['con']),
                    fn (Product $a, Product $b) => $compatibilidad[$a->id]['orden'] <=> $compatibilidad[$b->id]['orden'],
                    fn (Product $a, Product $b) => $a->id <=> $b->id,
                ])
                ->take(self::LIMITE)
                ->map(fn (Product $producto) => $this->armar($producto, collect(array_keys($compatibilidad[$producto->id]['con']))
                    ->map(fn ($id) => $titulosCarrito[$id] ?? null)
                    ->filter()
                    ->values()
                    ->all()))
                ->values();
        }

        // Con compatibilidad definida en el carrito (aunque todos sus compatibles ya estén o no tengan stock) tampoco
        // corresponde ofrecer genéricos.
        if ($pares->isEmpty() && $elegidos->count() < self::LIMITE) {
            $siempre = $candidatos()
                ->where('sugerir_en_carrito', true)
                ->orderBy('title')
                ->limit(self::LIMITE - $elegidos->count())
                ->get()
                ->map(fn (Product $producto) => $this->armar($producto, []));

            $elegidos = $elegidos->concat($siempre);
        }

        return $elegidos->values();
    }

    /**
     * @param  array<int, string>  $compatibleCon
     * @return array<string, mixed>
     */
    private function armar(Product $producto, array $compatibleCon): array
    {
        $precio = $this->pricingService->calcularPrecio($producto, 1);

        return [
            'id' => $producto->id,
            'title' => $producto->title,
            'image' => $producto->primaryImage()?->url,
            'price' => $precio->precioUnitarioFinal,
            'list_price' => $precio->precioLista,
            // Un producto con colores activos no se agrega sin elegir uno: la tienda lo manda a su ficha.
            'has_variants' => $producto->variantes_activas_count > 0,
            'compatible_con' => $compatibleCon,
        ];
    }
}
