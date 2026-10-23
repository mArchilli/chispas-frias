<?php

namespace App\Logging;

use Monolog\Formatter\LineFormatter;
use Monolog\LogRecord;

/**
 * Formateador de líneas de log que tapa mails y números largos (DNI, CUIT, teléfonos, tarjetas) DESPUÉS de armar la línea, así
 * alcanza al mensaje, al contexto y a las excepciones con su stack trace. Los ids, SKUs, montos y fechas del negocio quedan legibles.
 */
class FormateadorSinDatosPersonales extends LineFormatter
{
    public function format(LogRecord $record): string
    {
        return self::tapar(parent::format($record));
    }

    public static function tapar(string $linea): string
    {
        $linea = preg_replace('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/u', '[mail]', $linea) ?? $linea;
        // CUIT/CUIL con guiones o espacios (20-12345678-3, 20 12345678 3).
        $linea = preg_replace('/\b\d{2}[- ]\d{7,8}[- ]\d\b/', '[número]', $linea) ?? $linea;

        // Un número largo escrito con separadores (DNI «30.123.456», teléfono «+54 9 11 5555-6666», «(011) 4555-1234», tarjeta «4111 1111 1111 1111»):
        // se tapa si entre todos sus grupos suman 8 o más dígitos. Las fechas («2026-10-20») y las horas no se tocan.
        $linea = preg_replace_callback('/(?<![\w.:\/])(?!\d{4}-\d{2}-\d{2}(?!\d))\+?\(?\d[\d ()\-.]{5,}\d(?![\w:\/])/', function (array $m) {
            return strlen((string) preg_replace('/\D/', '', $m[0])) >= 8 ? '[número]' : $m[0];
        }, $linea) ?? $linea;

        // 8 o más dígitos seguidos: DNI, teléfono, tarjeta.
        return preg_replace('/(?<![\w.])\d{8,19}(?![\w])/', '[número]', $linea) ?? $linea;
    }
}
