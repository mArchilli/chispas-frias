<?php

namespace App\Services;

use App\Enums\MotivoMovimientoStock;
use App\Exceptions\StockInsuficienteException;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Chequeo optimista (sin locking) de disponibilidad de stock para un conjunto de
     * items. El chequeo definitivo, bajo lock, pasa dentro de `descontar()`.
     *
     * @param  array<int, array{product_id: int, cantidad: int}>  $items
     * @return array<int, array{product_id: int, stock_disponible: int, cantidad: int}>
     *         Vacío si hay stock suficiente para todos los items.
     */
    public function validarDisponibilidad(array $items): array
    {
        $faltantes = [];

        foreach ($items as $item) {
            $product = Product::find($item['product_id']);

            if (! $product || ! $product->tieneStockDisponible($item['cantidad'])) {
                $faltantes[] = [
                    'product_id' => $item['product_id'],
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
     * @throws StockInsuficienteException
     */
    public function descontar(Order $order): void
    {
        $items = $this->itemsConProducto($order);

        if ($items->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($order, $items) {
            $productos = $this->lockearProductos($items);

            foreach ($items as $item) {
                $product = $productos[$item->product_id];
                $cantidad = (int) $item->cantidad;

                // Chequeo definitivo: dentro del lock, no confiar en validarDisponibilidad().
                if (! $product->tieneStockDisponible($cantidad)) {
                    throw new StockInsuficienteException($product->id, $cantidad, $product->stock);
                }

                $product->stock -= $cantidad;
                $product->save();

                StockMovement::create([
                    'product_id' => $product->id,
                    'order_id' => $order->id,
                    'cantidad' => -$cantidad,
                    'motivo' => MotivoMovimientoStock::OrdenCreada,
                    'stock_resultante' => $product->stock,
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
            $productos = $this->lockearProductos($items);

            $yaRepuesta = StockMovement::where('order_id', $order->id)
                ->where('motivo', MotivoMovimientoStock::OrdenCancelada)
                ->exists();

            if ($yaRepuesta) {
                return;
            }

            foreach ($items as $item) {
                $product = $productos[$item->product_id];
                $cantidad = (int) $item->cantidad;

                $product->stock += $cantidad;
                $product->save();

                StockMovement::create([
                    'product_id' => $product->id,
                    'order_id' => $order->id,
                    'cantidad' => $cantidad,
                    'motivo' => MotivoMovimientoStock::OrdenCancelada,
                    'stock_resultante' => $product->stock,
                ]);
            }
        });
    }

    /**
     * Items de la orden con producto vivo (ignora `product_id = null`), ordenados por
     * `product_id` para lockear siempre en el mismo orden entre llamadas concurrentes.
     */
    private function itemsConProducto(Order $order): Collection
    {
        return $order->items()
            ->whereNotNull('product_id')
            ->orderBy('product_id')
            ->get(['product_id', 'cantidad']);
    }

    /**
     * Lockea (`FOR UPDATE`) los productos de los items dados, en el orden en que
     * vienen (ya ordenados por product_id por `itemsConProducto`).
     *
     * @return array<int, Product>
     */
    private function lockearProductos(Collection $items): array
    {
        $productos = [];

        foreach ($items as $item) {
            $productos[$item->product_id] = Product::where('id', $item->product_id)
                ->lockForUpdate()
                ->first();
        }

        return $productos;
    }
}
