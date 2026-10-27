<?php

namespace App\Services;

use App\Models\Combo;
use App\Models\Product;
use App\Support\Stock;

/**
 * Cuánto hay disponible HOY para cada línea del carrito, para que la web ajuste cantidades y avise ANTES del checkout.
 *
 * Lee solo las columnas locales de stock (`products.stock` y `product_variants.stock`): con INTEGRACION_STOCK son el espejo
 * del stock que publica el Central, así que no hay ninguna llamada HTTP. Es un dato orientativo: el chequeo que manda sigue
 * siendo el del checkout (StockService / reserva en el Central). NO escribe nada: quien llama decide qué hacer con el resultado.
 *
 * Stock compartido. Todo lo que consume la misma existencia comparte el disponible: dos líneas del mismo producto y color (que
 * solo difieren en personalizaciones), y un producto suelto junto con el mismo producto dentro de un combo. El disponible se
 * reparte en el orden del carrito y la suma nunca lo supera. Productos de stock ilimitado (9999) y variantes sin stock (NULL)
 * no se limitan; una línea sin ninguna restricción tiene `tope` null.
 *
 * Una línea cuyo producto/variante/combo ya no existe se omite (las vistas del carrito la descartan igual).
 */
class StockDelCarrito
{
    /**
     * Por `line_key`:
     *  - `cantidad`: lo que queda tras ajustar (una línea agotada conserva la suya: el cliente la saca a mano);
     *  - `ajustada_de`: lo que pedía la línea cuando hubo que bajarla (null si no se tocó);
     *  - `tope`: lo máximo que puede tener esa línea dado lo que consumen las demás (null = sin límite);
     *  - `sin_stock`: agotada.
     *
     * @param  array<int, array<string, mixed>>  $lineas  Carrito ya normalizado (`CartController::normalizeCart`).
     * @return array<string, array{cantidad: int, tope: int|null, sin_stock: bool, ajustada_de: int|null}>
     */
    public function revisar(array $lineas): array
    {
        $productos = Product::with('variantsActive')
            ->whereIn('id', collect($lineas)->pluck('product_id')->filter()->all())
            ->get()
            ->keyBy('id');
        $combos = Combo::with(['items.product.variantsActive'])
            ->whereIn('id', collect($lineas)->pluck('combo_id')->filter()->all())
            ->get()
            ->keyBy('id');

        /** @var array<string, int> $restante Unidades que quedan por pozo de stock (producto o variante). */
        $restante = [];
        /** @var array<string, array<int, array{pozo: string, por_unidad: int}>> $necesidades */
        $necesidades = [];
        $resultado = [];

        // Pasada 1: reparto en el orden del carrito.
        foreach ($lineas as $linea) {
            $necesita = isset($linea['combo_id'])
                ? $this->necesidadesDeCombo($linea, $combos->get($linea['combo_id']), $restante)
                : $this->necesidadesDeProducto($linea, $productos->get($linea['product_id']), $restante);

            if ($necesita === null) {
                continue;
            }

            $pedida = (int) $linea['quantity'];
            $permitida = $pedida;

            foreach ($necesita as $n) {
                $permitida = min($permitida, intdiv($restante[$n['pozo']], $n['por_unidad']));
            }

            $permitida = max(0, $permitida);

            foreach ($necesita as $n) {
                $restante[$n['pozo']] -= $permitida * $n['por_unidad'];
            }

            $necesidades[$linea['line_key']] = $necesita;
            $resultado[$linea['line_key']] = [
                'cantidad' => $permitida === 0 ? $pedida : $permitida,
                'tope' => $permitida,
                'sin_stock' => $necesita !== [] && $permitida === 0,
                'ajustada_de' => $necesita !== [] && $permitida > 0 && $permitida < $pedida ? $pedida : null,
            ];
        }

        // Pasada 2: el tope de cada línea es lo suyo más lo que sobra en sus pozos.
        foreach ($necesidades as $clave => $necesita) {
            if ($necesita === []) {
                $resultado[$clave]['tope'] = null;
            } elseif ($resultado[$clave]['sin_stock']) {
                $resultado[$clave]['tope'] = 0;
            } else {
                $sobra = min(array_map(fn (array $n) => intdiv($restante[$n['pozo']], $n['por_unidad']), $necesita));
                $resultado[$clave]['tope'] = $resultado[$clave]['cantidad'] + $sobra;
            }
        }

        return $resultado;
    }

    /**
     * @param  array<string, int>  $restante
     * @return array<int, array{pozo: string, por_unidad: int}>|null null = línea que no se puede evaluar; [] = sin límite.
     */
    private function necesidadesDeProducto(array $linea, ?Product $producto, array &$restante): ?array
    {
        if ($producto === null) {
            return null;
        }

        $pozo = $this->pozo($producto, $linea['variant_id'] ?? null, $restante);

        if ($pozo === false) {
            return null;
        }

        return $pozo === null ? [] : [['pozo' => $pozo, 'por_unidad' => 1]];
    }

    /**
     * @param  array<string, int>  $restante
     * @return array<int, array{pozo: string, por_unidad: int}>|null
     */
    private function necesidadesDeCombo(array $linea, ?Combo $combo, array &$restante): ?array
    {
        if ($combo === null) {
            return null;
        }

        $seleccion = collect($linea['component_selections'] ?? [])->keyBy('product_id');
        $necesita = [];

        foreach ($combo->items as $item) {
            if ($item->product === null) {
                continue;
            }

            $pozo = $this->pozo($item->product, $seleccion->get($item->product_id)['variant_id'] ?? null, $restante);

            if (is_string($pozo)) {
                $necesita[] = ['pozo' => $pozo, 'por_unidad' => max(1, (int) $item->quantity)];
            }
        }

        return $necesita;
    }

    /**
     * Pozo de stock del que sale una unidad: la variante elegida o, sin variante, el producto. Lo registra en `$restante` la
     * primera vez que aparece.
     *
     * @param  array<string, int>  $restante
     * @return string|null|false clave del pozo; null = ilimitado; false = no hay un número que dar (variante inexistente o
     *                           producto con colores sin elegir uno).
     */
    private function pozo(Product $producto, ?int $varianteId, array &$restante): string|null|false
    {
        if ($varianteId !== null) {
            $variante = $producto->variantsActive->firstWhere('id', $varianteId);

            if ($variante === null) {
                return false;
            }

            if (Stock::esIlimitado($variante->stock)) {
                return null;
            }

            $clave = "v:{$variante->id}";
            $restante[$clave] ??= max(0, (int) $variante->stock);

            return $clave;
        }

        // Un producto con colores activos se compra eligiendo uno: sin él no hay un número que informar.
        if ($producto->variantsActive->isNotEmpty()) {
            return false;
        }

        if (Stock::esIlimitado($producto->stock)) {
            return null;
        }

        $clave = "p:{$producto->id}";
        $restante[$clave] ??= max(0, (int) $producto->stock);

        return $clave;
    }
}
