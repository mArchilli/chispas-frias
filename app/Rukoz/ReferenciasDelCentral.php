<?php

namespace App\Rukoz;

use Illuminate\Support\Facades\Cache;
use Rukoz\CoreClient\Errores\RukozException;
use Rukoz\CoreClient\Facades\Rukoz;

/**
 * Lo que el modal «Confirmar venta» necesita del Central (`GET /referencias`): cuentas de cobro, medios de pago, formas de
 * envío, transportistas, puntos de atención (10.4) y vendedores ya asignados.
 *
 * Doble caché: el del paquete (por `hash`, una consulta liviana) y, encima, uno de la web de `integracion.referencias_cache`
 * segundos, para que abrir el modal no pegue al Central cada vez. Si el Central no contesta se usa la lista configurable
 * (`integracion.referencias_respaldo`); `origen` dice cuál se usó y `error` por qué falló el Central.
 *
 * Nada de esto lleva datos de clientes.
 */
final class ReferenciasDelCentral
{
    private const CLAVE = 'rukoz:referencias:ui';

    /**
     * @return array{origen: 'central'|'respaldo'|'ninguno', error: string|null, cuentas: list<array<string, mixed>>, medios_pago: list<array<string, mixed>>, formas_envio: list<array<string, mixed>>, transportistas: list<array<string, mixed>>, puntos: list<array<string, mixed>>, vendedores: list<array<string, mixed>>}
     */
    public static function obtener(bool $forzar = false): array
    {
        if (! $forzar && is_array($guardado = Cache::get(self::CLAVE))) {
            return $guardado;
        }

        try {
            $datos = self::normalizar(Rukoz::referenciasConCache(), 'central', null);
            Cache::put(self::CLAVE, $datos, max(1, (int) config('integracion.referencias_cache')));

            return $datos;
        } catch (RukozException $e) {
            ErroresDelCentral::registrar($e, 'leer las referencias para confirmar');
            $error = 'No pudimos leer las cuentas y formas de envío del Sistema Central.';
        }

        $respaldo = config('integracion.referencias_respaldo');

        // El respaldo no se cachea: apenas el Central vuelva se lo prefiere.
        return is_array($respaldo) && $respaldo !== []
            ? self::normalizar($respaldo, 'respaldo', $error)
            : self::normalizar([], 'ninguno', $error);
    }

    public static function olvidar(): void
    {
        Cache::forget(self::CLAVE);
    }

    /** @param array<string, mixed> $datos */
    private static function normalizar(array $datos, string $origen, ?string $error): array
    {
        $lista = fn (string $clave) => array_values(array_filter((array) ($datos[$clave] ?? []), 'is_array'));

        return [
            'origen' => $origen,
            'error' => $error,
            'cuentas' => $lista('cuentas'),
            'medios_pago' => $lista('medios_pago'),
            'formas_envio' => $lista('formas_envio'),
            'transportistas' => $lista('transportistas'),
            'puntos' => $lista('puntos'),
            'vendedores' => $lista('vendedores'),
        ];
    }
}
