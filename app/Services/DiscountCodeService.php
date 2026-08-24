<?php

namespace App\Services;

use App\Exceptions\DiscountCodeInvalidoException;
use App\Models\DiscountCode;
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
     * `registrarUso()` concurrentes. La idempotencia (no reponer dos veces la
     * misma orden) es responsabilidad de quien llama, apoyándose en el flag
     * `orders.discount_usage_repuesto`.
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
