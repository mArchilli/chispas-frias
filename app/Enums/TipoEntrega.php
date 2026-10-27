<?php

namespace App\Enums;

/**
 * Cómo recibe el pedido quien compra (checkout mínimo): envío con transportista o retiro en la oficina de Buenos Aires (hay una sola;
 * el cliente no la elige). Un pedido sin tipo guardado (anterior al checkout mínimo) es un envío.
 */
enum TipoEntrega: string
{
    case Envio = 'envio';
    case Retiro = 'retiro';

    /** Lo que se muestra del retiro en el checkout, el mensaje de WhatsApp y el admin. */
    public const OFICINA = 'Retiro en oficina · Buenos Aires';

    public function label(): string
    {
        return match ($this) {
            self::Envio => 'Envío',
            self::Retiro => self::OFICINA,
        };
    }
}
