<?php

namespace App\Rukoz;

/**
 * Chequeos de seguridad de la conexión con el Sistema Central (S.2 · B.3).
 *
 * La API key viaja en cada request y el cuerpo lleva datos de clientes (nombre, teléfono, DNI, dirección): con una URL `http://`
 * todo eso circula en claro. En producción la URL del Central tiene que ser `https`. En desarrollo se tolera `http` solo hacia el
 * propio equipo (localhost, 127.0.0.1) para poder probar contra un Central local.
 */
class SeguridadDelCentral
{
    /** Qué está mal con la URL del Central (null si está bien o la integración está apagada). */
    public static function advertenciaTls(): ?string
    {
        if (! config('rukoz.habilitada')) {
            return null;
        }

        $url = (string) config('rukoz.url');

        if ($url === '') {
            return null; // la falta de URL ya la informa el paquete
        }

        $partes = parse_url($url);
        $esquema = strtolower($partes['scheme'] ?? '');
        $host = strtolower($partes['host'] ?? '');

        if ($esquema === 'https') {
            return null;
        }

        $local = in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true);

        if ($local && ! app()->isProduction()) {
            return null;
        }

        return 'RUKOZ_CENTRAL_URL ('.$url.') no usa https: la API key y los datos de los clientes viajan sin cifrar'.($local ? ' (y apunta a este mismo equipo: en producción no tiene sentido)' : '').'. Usá la URL https del Central.';
    }
}
