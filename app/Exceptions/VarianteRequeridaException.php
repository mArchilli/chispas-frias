<?php

namespace App\Exceptions;

use Exception;

/**
 * Se lanza cuando la resolución de opciones de un producto (variante de color +
 * add-ons de personalización) dentro de PricingService no puede completarse:
 * falta una variante obligatoria, o el varianteId/addonId recibido no pertenece
 * al producto o no está activo. Mismo espíritu que DiscountCodeInvalidoException:
 * constructor privado + named constructors con el motivo específico.
 */
class VarianteRequeridaException extends Exception
{
    private function __construct(
        string $message,
        public readonly int $productId,
        public readonly ?int $varianteId = null,
        public readonly ?int $addonId = null,
    ) {
        parent::__construct($message);
    }

    /**
     * El producto tiene variantes de color activas y el caller exigió elegir una
     * (checkout / paso A6), pero no vino ningún varianteId.
     */
    public static function faltante(int $productId): self
    {
        return new self(
            "El producto #{$productId} tiene variantes de color activas: hay que elegir una antes de continuar.",
            $productId,
        );
    }

    /**
     * El varianteId recibido no corresponde a una variante activa de este producto.
     */
    public static function varianteNoDisponible(int $productId, int $varianteId): self
    {
        return new self(
            "La variante #{$varianteId} no pertenece al producto #{$productId} o no está activa.",
            $productId,
            varianteId: $varianteId,
        );
    }

    /**
     * El addonId recibido no corresponde a un add-on activo ofrecido por este producto.
     */
    public static function addonNoDisponible(int $productId, int $addonId): self
    {
        return new self(
            "El add-on #{$addonId} no pertenece al producto #{$productId} o no está activo.",
            $productId,
            addonId: $addonId,
        );
    }
}
