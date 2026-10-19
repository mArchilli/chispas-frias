<?php

namespace App\Services;

use App\Exceptions\DiscountCodeInvalidoException;
use App\Models\DiscountCode;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class DiscountCodeService
{
    /**
     * Busca un código de descuento por su texto (case-insensitive) y valida que
     * pueda usarse para un carrito con el subtotal dado: vigencia (fechas,
     * is_active, no agotado) y `min_purchase_amount`.
     *
     * @throws DiscountCodeInvalidoException con el motivo específico de invalidez.
     */
    public function buscarValido(string $code, float $subtotal): DiscountCode
    {
        $codeNormalizado = strtoupper(trim($code));

        $discountCode = DiscountCode::where('code', $codeNormalizado)->first();

        if (! $discountCode) {
            throw DiscountCodeInvalidoException::noExiste($codeNormalizado);
        }

        if (! $this->activoPorFechas($discountCode)) {
            throw DiscountCodeInvalidoException::inactivoOExpirado($codeNormalizado);
        }

        if ($discountCode->agotado()) {
            throw DiscountCodeInvalidoException::agotado($codeNormalizado);
        }

        if ($discountCode->min_purchase_amount !== null && $subtotal < (float) $discountCode->min_purchase_amount) {
            throw DiscountCodeInvalidoException::noAlcanzaMinimo(
                $codeNormalizado,
                (float) $discountCode->min_purchase_amount,
                $subtotal
            );
        }

        return $discountCode;
    }

    /**
     * Monto de descuento para un subtotal dado, redondeado a 2 decimales igual
     * que hace PricingService con sus montos.
     */
    public function calcularDescuento(DiscountCode $discountCode, float $subtotal): float
    {
        return round($subtotal * ((float) $discountCode->percentage) / 100, 2);
    }

    /**
     * Consume UN uso del código de un pedido recién creado. IDEMPOTENTE: deja
     * `orders.discount_usage_consumido_at` y una segunda llamada para el mismo pedido (reintento,
     * evento duplicado) no suma nada. Tampoco consume un pedido cuyo uso ya se liberó
     * (consumir después de liberar dejaría el contador de más).
     *
     * Tiene que correr dentro de la transacción que crea el pedido. La marca se lee con el pedido
     * lockeado, así dos llamadas concurrentes tampoco consumen dos veces. Como `registrarUso()`,
     * revalida bajo lock que el código no se haya agotado (lanza DiscountCodeInvalidoException y
     * aborta la transacción del pedido).
     *
     * @throws DiscountCodeInvalidoException si el código quedó agotado justo antes de tomar el lock.
     */
    public function consumirUso(Order $order): void
    {
        if ($order->discount_code_id === null) {
            return;
        }

        DB::transaction(function () use ($order) {
            $actual = Order::whereKey($order->getKey())->lockForUpdate()->first();

            if ($actual === null
                || $actual->discount_code_id === null
                || $actual->discount_usage_consumido_at !== null
                || $actual->discount_usage_liberado_at !== null
                || $actual->discount_usage_repuesto) {
                return;
            }

            $discountCode = DiscountCode::find($actual->discount_code_id);

            if ($discountCode === null) {
                return;
            }

            $this->registrarUso($discountCode);

            $this->marcar($order, $actual, ['discount_usage_consumido_at' => now()]);
        });
    }

    /**
     * Libera UN uso del código de un pedido cancelado (contraparte de consumirUso()). IDEMPOTENTE:
     * la primera llamada devuelve el uso y deja `orders.discount_usage_liberado_at`; cualquier
     * llamada posterior para el mismo pedido no hace nada. Los pedidos anteriores a esa columna
     * ya liberados se reconocen por el booleano heredado `discount_usage_repuesto`, que se sigue
     * dejando en true. La marca se lee con el pedido lockeado, así dos llamadas concurrentes
     * tampoco liberan dos veces.
     *
     * El snapshot del código en el pedido (discount_code, discount_amount) NO se toca: solo se
     * devuelve el cupo.
     */
    public function liberarUso(Order $order): void
    {
        if ($order->discount_code_id === null) {
            return;
        }

        DB::transaction(function () use ($order) {
            $actual = Order::whereKey($order->getKey())->lockForUpdate()->first();

            if ($actual === null
                || $actual->discount_code_id === null
                || $actual->discount_usage_liberado_at !== null
                || $actual->discount_usage_repuesto) {
                return;
            }

            $this->reponerUso($actual->discount_code_id);

            $this->marcar($order, $actual, [
                'discount_usage_liberado_at' => now(),
                'discount_usage_repuesto' => true,
            ]);
        });
    }

    /**
     * Guarda las marcas en la fila y las refleja en la instancia que tiene quien llama, sin
     * dejarla "sucia" (para que un update() posterior suyo no las reescriba).
     *
     * @param  array<string, mixed>  $marcas
     */
    private function marcar(Order $order, Order $actual, array $marcas): void
    {
        $actual->forceFill($marcas)->save();

        foreach ($marcas as $columna => $valor) {
            $order->setAttribute($columna, $valor);
            $order->syncOriginalAttribute($columna);
        }
    }

    /**
     * Registra un uso del código, incrementando `usage_count`. Lockea la fila
     * (`FOR UPDATE`) y revalida bajo lock que no esté agotado antes de confiar en
     * el chequeo optimista previo de `buscarValido()` — mismo patrón que
     * StockService::descontar() revalida stock bajo lock. Crítico para que dos
     * checkouts concurrentes no exploten un `usage_limit` = 1.
     *
     * @throws DiscountCodeInvalidoException si el código quedó agotado justo antes de tomar el lock.
     */
    public function registrarUso(DiscountCode $discountCode): void
    {
        DB::transaction(function () use ($discountCode) {
            $locked = DiscountCode::where('id', $discountCode->id)
                ->lockForUpdate()
                ->first();

            if (! $locked || $locked->agotado()) {
                throw DiscountCodeInvalidoException::agotado($discountCode->code);
            }

            $locked->usage_count += 1;
            $locked->save();

            $discountCode->usage_count = $locked->usage_count;
        });
    }

    /**
     * Repone un uso del código (usage_count - 1, sin bajar de 0), pensado para
     * llamarse al cancelar una orden. Lockea la fila para evitar carreras con
     * `registrarUso()` concurrentes. Es la operación de bajo nivel sobre el
     * contador y NO es idempotente: los flujos de pedido usan `liberarUso()`,
     * que sí lo es.
     */
    public function reponerUso(int $discountCodeId): void
    {
        DB::transaction(function () use ($discountCodeId) {
            $discountCode = DiscountCode::where('id', $discountCodeId)
                ->lockForUpdate()
                ->first();

            if (! $discountCode) {
                return;
            }

            $discountCode->usage_count = max(0, $discountCode->usage_count - 1);
            $discountCode->save();
        });
    }

    /**
     * Vigencia por is_active + rango de fechas, sin considerar si está agotado
     * (para poder distinguir el motivo exacto de invalidez en `buscarValido()`).
     */
    private function activoPorFechas(DiscountCode $discountCode): bool
    {
        if (! $discountCode->is_active) {
            return false;
        }

        $now = now();

        if ($discountCode->start_date && $discountCode->start_date > $now) {
            return false;
        }

        if ($discountCode->end_date && $discountCode->end_date < $now) {
            return false;
        }

        return true;
    }
}
