<?php

namespace App\Rukoz;

use App\Models\Combo;
use App\Models\ComboItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Rukoz\CoreClient\Errores\RukozException;
use Rukoz\CoreClient\Facades\Rukoz;
use Throwable;

/**
 * Sync parcial del catálogo hacia el Central desde el panel de administración, DESPUÉS de guardar
 * (fuera de la transacción). Solo actúa con INTEGRACION_CATALOGO encendido y nunca rompe el
 * guardado: si el Central no está, el paquete deja los ids diferidos para el próximo sync
 * completo; si algo más falla se reporta y se sigue.
 *
 * Un combo depende de las variantes y del producto de sus componentes, así que al guardar un
 * producto se vuelven a mandar los combos que lo contienen, después del producto.
 *
 * Borrados: lo borrado ya no sale del `ProveedorCatalogo`, así que antes de borrar se arma su
 * ítem con `activo: false` (`productoParaBorrar`, `varianteParaBorrar`, `comboParaBorrar`) y se
 * avisa al Central justo después (`productoBorrado`, `comboBorrado`). Es "mejor esfuerzo": si el
 * Central no está, lo desactiva el sync completo (todo lo que no viene se desactiva allá).
 */
class SincronizacionDeCatalogo
{
    public static function activa(): bool
    {
        return (bool) config('integracion.catalogo');
    }

    /**
     * Producto guardado (alta, edición, activar/desactivar). Reenvía también los combos que lo contienen.
     *
     * @param  Collection<int, ProductVariant>|null  $variantesBorradas  variantes que se acaban de quitar del producto
     *                                                                   (se avisan como inactivas: ya no están en la base)
     */
    public static function producto(Product|int $producto, ?Collection $variantesBorradas = null): void
    {
        if (! self::activa()) {
            return;
        }

        $id = $producto instanceof Product ? $producto->getKey() : $producto;

        self::seguro(function () use ($id, $variantesBorradas) {
            Rukoz::sincronizarProductos([$id]);

            if ($variantesBorradas !== null && $variantesBorradas->isNotEmpty()) {
                self::avisarVariantesBorradas($id, $variantesBorradas);
            }

            self::combosDe([$id]);
        });
    }

    /**
     * Variantes que tiene el producto ahora, para comparar después de guardar y saber cuáles se quitaron.
     * Vacío (sin consultar) con la sincronización apagada.
     *
     * @return Collection<int, ProductVariant>
     */
    public static function variantesDe(Product $producto): Collection
    {
        return self::activa() ? $producto->variants()->get()->keyBy('id') : new Collection;
    }

    /**
     * Las variantes de `$antes` (ver `variantesDe`) que el producto ya no tiene.
     *
     * @param  Collection<int, ProductVariant>  $antes
     * @return Collection<int, ProductVariant>
     */
    public static function variantesQuitadas(Product $producto, Collection $antes): Collection
    {
        if ($antes->isEmpty()) {
            return $antes;
        }

        return $antes->except($producto->variants()->pluck('id')->all())->values();
    }

    /** Combo guardado (alta, edición, activar/desactivar). */
    public static function combo(Combo|int $combo): void
    {
        if (! self::activa()) {
            return;
        }

        $id = $combo instanceof Combo ? $combo->getKey() : $combo;

        self::seguro(fn () => Rukoz::sincronizarCombos([$id]));
    }

    /**
     * Ítem de un producto para avisar su borrado (con `activo: false`, él y sus variantes), armado ANTES de borrar.
     * Null si la sincronización está apagada (no hay nada que avisar).
     *
     * @return array<string, mixed>|null
     */
    public static function productoParaBorrar(Product $producto): ?array
    {
        if (! self::activa()) {
            return null;
        }

        $producto->loadMissing('variants');
        $item = (new CatalogoDeChispasFrias)->productoComoItem($producto);
        $item['activo'] = false;

        foreach ($item['variantes'] ?? [] as $i => $variante) {
            $item['variantes'][$i]['activo'] = false;
        }

        return $item;
    }

    /**
     * Ids de los combos que contienen el producto, para reenviarlos después de borrarlo (su receta cambia).
     * Hay que leerlos ANTES de borrar: el borrado se lleva los componentes en cascada.
     *
     * @return list<int>
     */
    public static function combosDelProducto(Product $producto): array
    {
        if (! self::activa()) {
            return [];
        }

        return ComboItem::query()->where('product_id', $producto->getKey())->pluck('combo_id')->unique()->values()->all();
    }

    /**
     * Avisa un producto borrado (ver `productoParaBorrar`) y reenvía los combos que lo contenían.
     *
     * @param  array<string, mixed>|null  $item
     * @param  list<int>  $combosAfectados
     */
    public static function productoBorrado(?array $item, array $combosAfectados = []): void
    {
        if (! self::activa() || $item === null) {
            return;
        }

        self::seguro(function () use ($item, $combosAfectados) {
            if (Rukoz::habilitada()) {
                Rukoz::syncCatalogo(['completo' => false, 'productos' => [$item], 'combos' => []]);
            }

            if ($combosAfectados !== []) {
                Rukoz::sincronizarCombos($combosAfectados);
            }
        });
    }

    /**
     * Ítem de un combo para avisar su borrado (con `activo: false`), armado ANTES de borrar. Null si está apagada
     * la sincronización o si el combo no tiene componentes (el Central no admite un combo vacío).
     *
     * @return array<string, mixed>|null
     */
    public static function comboParaBorrar(Combo $combo): ?array
    {
        if (! self::activa()) {
            return null;
        }

        $combo->loadMissing('items.product.variantsActive');

        if ($combo->items->isEmpty()) {
            return null;
        }

        $item = (new CatalogoDeChispasFrias)->comboComoItem($combo);
        $item['activo'] = false;

        return $item;
    }

    /**
     * @param  array<string, mixed>|null  $item
     */
    public static function comboBorrado(?array $item): void
    {
        if (! self::activa() || $item === null) {
            return;
        }

        self::seguro(function () use ($item) {
            if (Rukoz::habilitada()) {
                Rukoz::syncCatalogo(['completo' => false, 'productos' => [], 'combos' => [$item]]);
            }
        });
    }

    /**
     * Manda el producto otra vez con las variantes quitadas como inactivas (ya no están en la base, así que
     * el proveedor no las conoce).
     *
     * @param  Collection<int, ProductVariant>  $variantesBorradas
     */
    private static function avisarVariantesBorradas(int|string $productoId, Collection $variantesBorradas): void
    {
        if (! Rukoz::habilitada()) {
            return;
        }

        $producto = Product::query()->with('variants')->find($productoId);

        if ($producto === null) {
            return;
        }

        $catalogo = new CatalogoDeChispasFrias;
        $item = $catalogo->productoComoItem($producto);

        foreach ($variantesBorradas as $variante) {
            $item['variantes'][] = ['activo' => false] + $catalogo->varianteComoItem($producto, $variante);
        }

        Rukoz::syncCatalogo(['completo' => false, 'productos' => [$item], 'combos' => []]);
    }

    /** @param list<int|string> $productoIds */
    private static function combosDe(array $productoIds): void
    {
        $combos = Combo::query()
            ->whereHas('items', fn ($q) => $q->whereIn('product_id', $productoIds))
            ->pluck('id')
            ->all();

        if ($combos !== []) {
            Rukoz::sincronizarCombos($combos);
        }
    }

    private static function seguro(callable $accion): void
    {
        try {
            $accion();
        } catch (RukozException $e) {
            // El Central no está o rechazó el pedido: no es un error de la web. El sync completo diario lo corrige.
            Log::warning('Sync de catálogo con el Central no completado: '.$e->getMessage());
        } catch (Throwable $e) {
            report($e);
        }
    }
}
