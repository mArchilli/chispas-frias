<?php

namespace App\Services;

use App\Models\DiscountCode;
use Illuminate\Support\Collection;

/**
 * Resultado inmutable de CalculadoraPedido: el pedido ya calculado, sin haber escrito
 * nada en la base.
 *
 * Se arma en dos etapas (ver CalculadoraPedido): primero los ítems con sus precios
 * (`$codigoDescuento` null, `$total` = `$subtotal`) y después, con `conDescuentoYPlan()`,
 * el cupón validado y el plan de tarjeta.
 */
final class CalculoPedido
{
    /**
     * @param  Collection<int, array<string, mixed>>  $items  Ítems del carrito ya resueltos (producto o combo), con los mismos campos que consumen las vistas.
     * @param  array<int, array{product_id: int, product_variant_id: int|null, cantidad: int}>  $lineasStock  Unidades de stock que el pedido consume (combos expandidos a sus componentes).
     * @param  array{id: int, name: string, installments: int, surcharge_percentage: float, surcharge_amount: float, total_with_surcharge: float, installment_amount: float}|null  $planPago
     */
    public function __construct(
        public readonly Collection $items,
        public readonly float|int $subtotal,
        public readonly bool $envioGratisPorCombo,
        public readonly array $lineasStock,
        public readonly ?DiscountCode $codigoDescuento = null,
        public readonly float $descuento = 0.0,
        public readonly float $total = 0.0,
        public readonly ?array $planPago = null,
        public readonly ?string $motivoPlanDescartado = null,
    ) {}

    /**
     * Misma etapa de ítems, ahora con el cupón (ya validado, NO consumido) y el plan de pago.
     *
     * @param  array<string, mixed>|null  $planPago
     */
    public function conDescuentoYPlan(?DiscountCode $codigo, float $descuento, float $total, ?array $planPago, ?string $motivoPlanDescartado): self
    {
        return new self(
            $this->items,
            $this->subtotal,
            $this->envioGratisPorCombo,
            $this->lineasStock,
            $codigo,
            $descuento,
            $total,
            $planPago,
            $motivoPlanDescartado,
        );
    }
}
