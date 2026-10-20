<?php

namespace App\Rukoz;

use App\Models\Combo;
use App\Models\ComboItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Str;
use Rukoz\CoreClient\Contratos\ProveedorCatalogo;

/**
 * Catálogo de Chispas Frías en el formato de `POST /catalogo/sync` del Central. Solo LEE.
 *
 * Qué se manda:
 *  - Producto sin variantes: un ítem `producto`. Con variantes: el producto con su lista de `variantes`
 *    (todas, activas o no, cada una con su `activo`); en el Central se vincula cada variante, no el producto.
 *  - Precio: el de LISTA (`products.price`, y para una variante ese precio + `price_addon`, el recargo fijo
 *    del color). Es informativo para el Central: no se mandan escalas por cantidad, ofertas, descuentos ni el
 *    plan de tarjeta.
 *  - Stock: no viaja. El 9999 de los productos y el NULL (ilimitado) de las variantes son tema de la 10.2.
 *  - La variante «a elección del cliente» (`is_custom_color`) va como una variante más: en el Central se
 *    vincula al artículo genérico «color a elegir».
 *  - Combo sin componentes con color a elegir: `tipo: fijo`. Con alguno (o mixto): `tipo: seleccion` con UN
 *    grupo por componente (ver `gruposDe`).
 *  - Inactivos: van con `activo: false`. Una variante activa de un producto inactivo va inactiva. Un producto
 *    o combo BORRADO ya no existe en la base, así que no sale de acá: lo avisa `SincronizacionDeCatalogo`
 *    al borrar y, si no, lo desactiva el sync completo del Central (todo lo que no viene se desactiva).
 *
 * `sku`: el producto y la variante lo mandan si lo tienen cargado; el combo no tiene.
 */
class CatalogoDeChispasFrias implements ProveedorCatalogo
{
    /** @param list<int|string>|null $ids */
    public function productos(?array $ids = null): iterable
    {
        $consulta = Product::query()->with('variants');

        if ($ids !== null) {
            $consulta->whereIn('id', $ids);
        }

        foreach ($consulta->lazyById(200) as $producto) {
            yield $this->productoComoItem($producto);
        }
    }

    /** @param list<int|string>|null $ids */
    public function combos(?array $ids = null): iterable
    {
        $consulta = Combo::query()->with(['items.product.variantsActive']);

        if ($ids !== null) {
            $consulta->whereIn('id', $ids);
        }

        foreach ($consulta->lazyById(200) as $combo) {
            // Un combo sin componentes (se le borraron todos los productos) no es válido para el Central, y un
            // solo ítem inválido rechaza la parte entera: no se manda, y el sync completo lo desactiva allá.
            if ($combo->items->isEmpty()) {
                continue;
            }

            yield $this->comboComoItem($combo);
        }
    }

    /**
     * Un producto (con sus variantes si las tiene) en el formato del Central.
     *
     * @return array<string, mixed>
     */
    public function productoComoItem(Product $producto): array
    {
        $item = [
            'id' => (string) $producto->id,
            'nombre' => $producto->title,
            'sku' => $this->textoOnull($producto->sku),
            'precio' => (float) $producto->price,
            'activo' => (bool) $producto->is_active,
        ];

        if ($producto->variants->isNotEmpty()) {
            $item['variantes'] = $producto->variants
                ->map(fn (ProductVariant $variante) => $this->varianteComoItem($producto, $variante))
                ->values()
                ->all();
        }

        return $item;
    }

    /** @return array<string, mixed> */
    public function varianteComoItem(Product $producto, ProductVariant $variante): array
    {
        return [
            'id' => (string) $variante->id,
            'nombre' => $variante->name,
            'sku' => $this->textoOnull($variante->sku),
            'precio' => (float) $producto->price + (float) $variante->price_addon,
            'activo' => (bool) $variante->is_active && (bool) $producto->is_active,
        ];
    }

    /**
     * Un combo en el formato del Central.
     *
     * @return array<string, mixed>
     */
    public function comboComoItem(Combo $combo): array
    {
        $item = [
            'id' => (string) $combo->id,
            'nombre' => $combo->title,
            'precio' => (float) $combo->price,
            'activo' => (bool) $combo->is_active,
        ];

        if ($combo->items->contains(fn (ComboItem $componente) => $this->requiereSeleccionDeColor($componente))) {
            return $item + ['tipo' => 'seleccion', 'grupos' => $this->gruposDe($combo)];
        }

        return $item + ['tipo' => 'fijo', 'items' => $this->itemsFijosDe($combo)];
    }

    /**
     * Combo fijo: cada componente es el producto con su cantidad. El Central no admite el mismo ítem dos veces
     * en un combo: la base tampoco deja repetir un producto (`unique(combo_id, product_id)`), pero si pasara
     * se suman las cantidades.
     *
     * @return list<array{tipo: string, id: string, cantidad: int}>
     */
    private function itemsFijosDe(Combo $combo): array
    {
        $items = [];

        foreach ($combo->items as $componente) {
            $id = (string) $componente->product_id;

            if (isset($items[$id])) {
                $items[$id]['cantidad'] += (int) $componente->quantity;

                continue;
            }

            $items[$id] = ['tipo' => 'producto', 'id' => $id, 'cantidad' => (int) $componente->quantity];
        }

        return array_values($items);
    }

    /**
     * Combo con elección (o mixto): un grupo por componente de la receta, con la cantidad del componente.
     *  - componente sin color a elegir → grupo de UNA sola opción (el producto);
     *  - componente con color a elegir → grupo con todas las variantes activas del producto como opciones
     *    (es lo que el cliente puede elegir en el checkout, incluida la «a elección» si el producto la tiene).
     *
     * El nombre del grupo tiene que ser único dentro del combo y estable: «{producto} (#{id del componente})».
     * Ese id (`combo_items.id`) es la clave de la línea de la receta, así que el nombre no cambia si se
     * reordenan los componentes.
     *
     * @return list<array{nombre: string, cantidad: int, opciones: list<array{tipo: string, id: string}>}>
     */
    private function gruposDe(Combo $combo): array
    {
        $grupos = [];

        foreach ($combo->items as $componente) {
            $opciones = $this->requiereSeleccionDeColor($componente)
                ? $componente->product->variantsActive
                    ->map(fn (ProductVariant $variante) => ['tipo' => 'variante', 'id' => (string) $variante->id])
                    ->values()
                    ->all()
                : [['tipo' => 'producto', 'id' => (string) $componente->product_id]];

            $grupos[] = [
                'nombre' => self::nombreDeGrupo($componente),
                'cantidad' => (int) $componente->quantity,
                'opciones' => $opciones,
            ];
        }

        return $grupos;
    }

    /** Nombre del grupo de un componente del combo (ver `gruposDe`). */
    public static function nombreDeGrupo(ComboItem $componente): string
    {
        return Str::limit((string) $componente->product->title, 200, '').' (#'.$componente->id.')';
    }

    /**
     * El cliente elige color de este componente cuando el producto tiene variantes activas (es la misma regla
     * que usa el checkout: CalculadoraPedido exige variante si hay alguna activa).
     */
    private function requiereSeleccionDeColor(ComboItem $componente): bool
    {
        return $componente->product !== null && $componente->product->variantsActive->isNotEmpty();
    }

    private function textoOnull(?string $valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }
}
