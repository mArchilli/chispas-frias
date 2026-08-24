<?php

namespace App\Exceptions;

use Exception;

class DiscountCodeInvalidoException extends Exception
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    /**
     * No existe ningún código de descuento con ese texto.
     */
    public static function noExiste(string $code): self
    {
        return new self("El código \"{$code}\" no existe.");
    }

    /**
     * El código existe pero no está vigente: desactivado manualmente o fuera de
     * su rango de fechas (start_date / end_date).
     */
    public static function inactivoOExpirado(string $code): self
    {
        return new self("El código \"{$code}\" no está vigente.");
    }

    /**
     * El código alcanzó su límite de usos (usage_limit).
     */
    public static function agotado(string $code): self
    {
        return new self("El código \"{$code}\" ya alcanzó su límite de usos.");
    }

    /**
     * El subtotal del carrito no alcanza el mínimo de compra requerido por el código.
     */
    public static function noAlcanzaMinimo(string $code, float $minimo, float $subtotal): self
    {
        return new self(
            "El código \"{$code}\" requiere una compra mínima de $" . number_format($minimo, 2)
                . " (subtotal actual: $" . number_format($subtotal, 2) . ")."
        );
    }
}
