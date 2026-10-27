<?php

namespace App\Rukoz;

use App\Models\Order;
use App\Support\Provincias;
use Closure;
use Illuminate\Validation\Rule;

/**
 * Los datos del cliente que el empaquetador necesita SÍ O SÍ para despachar con un transportista: DNI, localidad, código postal y
 * teléfono. El checkout ya no los pide (salvo el teléfono): el vendedor los completa en la ficha del pedido o en el modal «Confirmar
 * venta», y un envío con transportista no se confirma sin ellos. Un retiro en oficina no pide ninguno.
 *
 * Los nombres de campo son las columnas de `orders`.
 */
final class DatosDeEnvio
{
    /** @var array<string, string> columna => cómo se nombra en los avisos */
    public const REQUERIDOS = [
        'dni' => 'DNI',
        'city' => 'localidad',
        'postal_code' => 'código postal',
        'phone' => 'teléfono',
    ];

    /** Los campos del cliente que se pueden guardar desde el admin (los demás datos del pedido no se tocan). */
    public const EDITABLES = ['name', 'phone', 'dni', 'email', 'province', 'city', 'postal_code', 'observations'];

    /** Teléfono con al menos 8 dígitos (característica incluida): la misma regla en el checkout, la ficha y el modal. */
    public static function reglaTelefono(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falla) {
            if ($valor !== null && $valor !== '' && strlen((string) preg_replace('/\D+/', '', (string) $valor)) < 8) {
                $falla('Ingresá un teléfono con al menos 8 dígitos (con característica).');
            }
        };
    }

    /**
     * Reglas de validación de los datos del cliente que se pueden guardar desde el admin. Solo el nombre es obligatorio (y solo cuando
     * se manda): lo que falte para despachar lo exige el modal de confirmación, no el guardado.
     *
     * @return array<string, mixed>
     */
    public static function reglas(string $prefijo = ''): array
    {
        return [
            $prefijo.'name' => ['sometimes', 'required', 'string', 'min:2', 'max:100'],
            $prefijo.'phone' => ['sometimes', 'nullable', 'string', 'max:30', self::reglaTelefono()],
            $prefijo.'dni' => ['sometimes', 'nullable', 'string', 'max:20'],
            $prefijo.'email' => ['sometimes', 'nullable', 'email', 'max:150'],
            $prefijo.'province' => ['sometimes', 'nullable', 'string', Rule::in(Provincias::slugs())],
            $prefijo.'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            $prefijo.'postal_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            $prefijo.'observations' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Qué datos de envío faltan (columna => nombre), mirando el pedido y, encima, lo que se está por guardar.
     *
     * @param  array<string, mixed>  $nuevos  valores a guardar (solo las claves que vienen pisan al pedido)
     * @return array<string, string>
     */
    public static function faltantes(Order $pedido, array $nuevos = []): array
    {
        $faltan = [];

        foreach (self::REQUERIDOS as $columna => $nombre) {
            $valor = array_key_exists($columna, $nuevos) ? $nuevos[$columna] : $pedido->{$columna};

            if (trim((string) $valor) === '') {
                $faltan[$columna] = $nombre;
            }
        }

        return $faltan;
    }
}
