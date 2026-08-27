<?php

namespace App\Enums;

enum TipoDocumento: string
{
    case Link = 'link';
    case Pdf = 'pdf';

    /**
     * Etiqueta legible para mostrar en la UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Link => 'Enlace',
            self::Pdf => 'PDF',
        };
    }
}
