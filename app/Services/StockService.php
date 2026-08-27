<?php

namespace App\Services;

use App\Enums\MotivoMovimientoStock;
use App\Exceptions\StockInsuficienteException;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Chequeo optimista (sin locking) de disponibilidad de stock para un conjunto de
     * items. El chequeo definitivo, bajo lock, pasa dentro de `descontar()`.
     *
     * Cuando un item trae `product_variant_id`, el chequeo se hace sobre el stock de
     * esa variante y no sobre el del producto. Devuelve un array vacío si hay stock
     * suficiente para todos los items.
     *
     * @param  array<int, array{product_id: int, product_variant_id?: int|null, cantidad: int}>  $items
     * @return array<int, array{product_id: int, product_variant_id: int|null, stock_disponible: int, cantidad: int}>
     */
    public function validarDisponibilidad(array $items): array
    {
        $faltantes = [];

        foreach ($items as $item) {
            $varianteId = $item['product_variant_id'] ?? null;

            if ($varianteId !== null) {
                $variante = ProductVariant::find($varianteId);

                if (! $variante || ! $variante->tieneStockDisponible($item['cantidad'])) {
                    $faltantes[] = [
                        'product_id' => $item['product_id'],
                        'product_variant_id' => $varianteId,
                        'stock_disponible' => $variante?->stock ?? 0,
                        'cantidad' => $item['cantidad'],
                    ];
                }

                continue;
            }

            $product = Product::find($item['product_id']);

            if (! $product || ! $product->tieneStockDisponible($item['cantidad'])) {
                $faltantes[] = [
                    'product_id' => $item['product_id'],
                    'product_variant_id' => null,
                    'stock_disponible' => $product?->stock ?? 0,
                    'cantidad' => $item['cantidad'],
                ];
            }
        }

        return $faltantes;
    }

    /**
     * Descuenta stock de los productos de la orden. Todo o nada: si algún item no
     * tiene stock suficiente se aborta la transacción completa y ningún producto
     * queda modificado.
     *
     * Los items con `product_variant_id` descuentan del stock de la variante; el
     * resto, del stock del producto.
     *
     * @throws StockInsuficienteException
     */
    public function descontar(Order $order): void
    {
        $items = $this->itemsConProducto($order);

        if ($items->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($order, $items) {
            [$productos, $variantes] = $this->lockearStock($items);

            foreach ($items as $item) {
                $cantidad = (int) $item->cantidad;
                $sujeto = $this->sujetoStock($item, $productos, $variantes);

                // Variante con stock ilimitado (stock null, mismo criterio que
                // products.stock): no hay nada que descontar ni que registrar.
                if ($sujeto->stock === null) {
                    continue;
                }

                // Chequeo definitivo: dentro del lock, no confiar en validarDisponibilidad().
                if (! $sujeto->tieneStockDisponible($cantidad)) {
                    throw new StockInsuficienteException($item->product_id, $cantidad, (int) $sujeto->stock);
                }

                $sujeto->stock -= $cantidad;
                $sujeto->save();

                StockMovement::create([
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'order_id' => $order->id,
                    'cantidad' => -$cantidad,
                    'motivo' => MotivoMovimientoStock::OrdenCreada,
                    'stock_resultante' => $sujeto->stock,
                ]);
            }
        });
    }

    /**
     * Repone el stock de los productos de la orden (p. ej. al cancelarla).
     *
     * Protección contra doble ejecución: la reposición ya realizada se detecta
     * chequeando si existen `StockMovement` con motivo `OrdenCancelada` para esta
     * orden. Ese chequeo se hace DENTRO de la misma transacción, después de tomar el
     * lock de los productos — así, si dos llamadas a `reponer()` para la misma orden
     * se solapan en el tiempo, la segunda queda bloqueada por el lock hasta que la
     * primera commitea, y al continuar ve el movimiento ya creado y no duplica nada.
     */
    public function reponer(Order $order): void
    {
        $items = $this->itemsConProducto($order);

        if ($items->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($order, $items) {
            [$productos, $variantes] = $this->lockearStock($items);

            $yaRepuesta = StockMovement::where('order_id', $order->id)
                ->where('motivo', MotivoMovimientoStock::OrdenCancelada)
                ->exists();

            if ($yaRepuesta) {
                return;
            }

            foreach ($items as $item) {
                $cantidad = (int) $item->cantidad;
                $sujeto = $this->sujetoStock($item, $productos, $variantes);

                // Variante con stock ilimitado: nada que reponer ni que registrar
                // (tampoco se le descontó nada en `descontar()`).
                if ($sujeto->stock === null) {
                    continue;
                }

                $sujeto->stock += $cantidad;
                $sujeto->save();

                StockMovement::create([
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'order_id' => $order->id,
                    'cantidad' => $cantidad,
                    'motivo' => MotivoMovimientoStock::OrdenCancelada,
                    'stock_resultante' => $sujeto->stock,
                ]);
            }
        });
    }

    /**
     * Items de la orden con producto vivo (ignora `product_id = null`), ordenados por
     * `product_id` y luego por `product_variant_id` para lockear siempre en el mismo
     * orden entre llamadas concurrentes.
     */
    private function itemsConProducto(Order $order): Collection
    {
        return $order->items()
            ->whereNotNull('product_id')
            ->orderBy('product_id')
            ->orderBy('product_variant_id')
            ->get(['product_id', 'product_variant_id', 'cantidad']);
    }

    /**
     * Lockea (`FOR UPDATE`) el stock que van a tocar los items: primero todos los
     * productos, después todas las variantes, cada nivel en orden ascendente de id.
     * El orden fijo (product_id, luego product_variant_id) es el que evita deadlocks
     * entre dos checkouts concurrentes sobre los mismos productos/variantes.
     *
     * Los productos con variante también se lockean a nivel producto: mantiene el
     * orden de lock uniforme y no cuesta nada (una fila que igual no se modifica).
     *
     * @return array{0: array<int, Product>, 1: array<int, ProductVariant>}
     */
    private function lockearStock(Collection $items): array
    {
        $productIds = $items->pluck('product_id')->unique()->sort()->values();
        $varianteIds = $items->pluck('product_variant_id')->filter()->unique()->sort()->values();

        $productos = [];
        foreach ($productIds as $productId) {
            $productos[$productId] = Product::where('id', $productId)
                ->lockForUpdate()
                ->first();
        }

        $variantes = [];
        foreach ($varianteIds as $varianteId) {
            $variantes[$varianteId] = ProductVariant::where('id', $varianteId)
                ->lockForUpdate()
                ->first();
        }

        return [$productos, $variantes];
    }

    /**
     * El modelo cuyo stock toca este item: la variante si el item la trae, si no
     * el producto. Ambos ya vienen lockeados por `lockearStock()`.
     *
     * @param  array<int, Product>  $productos
     * @param  array<int, ProductVariant>  $variantes
     * @return Product|ProductVariant
     */
    private function sujetoStock(object $item, array $productos, array $variantes): object
    {
        return $item->product_variant_id !== null
            ? $variantes[$item->product_variant_id]
            : $productos[$item->product_id];
    }
}
