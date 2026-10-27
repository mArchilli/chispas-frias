<?php

namespace App\Support;

/**
 * Fuente única de las 24 jurisdicciones que ofrece el checkout público. CartController::checkout() la usa para armar el
 * selector de provincia; OrderController y PedidoParaCentral la usan para traducir el slug guardado en orders.province a un
 * nombre legible.
 *
 * Los slugs de las ocho provincias que ya existían (buenos-aires, caba, cordoba, santa-fe, mendoza, tucuman, salta,
 * entre-rios) NO cambian: los pedidos viejos los tienen guardados. Las 16 nuevas usan el nombre en minúsculas, sin acentos y
 * con guiones.
 */
class Provincias
{
    /** @var array<string, string> slug => nombre */
    private const NOMBRES = [
        'buenos-aires' => 'Buenos Aires',
        'caba' => 'Ciudad Autónoma de Buenos Aires',
        'catamarca' => 'Catamarca',
        'chaco' => 'Chaco',
        'chubut' => 'Chubut',
        'cordoba' => 'Córdoba',
        'corrientes' => 'Corrientes',
        'entre-rios' => 'Entre Ríos',
        'formosa' => 'Formosa',
        'jujuy' => 'Jujuy',
        'la-pampa' => 'La Pampa',
        'la-rioja' => 'La Rioja',
        'mendoza' => 'Mendoza',
        'misiones' => 'Misiones',
        'neuquen' => 'Neuquén',
        'rio-negro' => 'Río Negro',
        'salta' => 'Salta',
        'san-juan' => 'San Juan',
        'san-luis' => 'San Luis',
        'santa-cruz' => 'Santa Cruz',
        'santa-fe' => 'Santa Fe',
        'santiago-del-estero' => 'Santiago del Estero',
        'tierra-del-fuego' => 'Tierra del Fuego',
        'tucuman' => 'Tucumán',
    ];

    /**
     * @return array<string, array{name: string}> slug => ['name' => nombre], en el orden del selector.
     */
    public static function all(): array
    {
        return array_map(fn (string $nombre) => ['name' => $nombre], self::NOMBRES);
    }

    /** @return list<string> */
    public static function slugs(): array
    {
        return array_keys(self::NOMBRES);
    }

    /**
     * Nombre legible para un slug de provincia (ej. "buenos-aires" -> "Buenos Aires").
     * Fallback a un título armado a partir del slug si no está en el catálogo,
     * para no romper con datos viejos/manuales.
     */
    public static function nombre(?string $slug): ?string
    {
        if (!$slug) {
            return null;
        }

        return self::NOMBRES[$slug] ?? ucwords(str_replace('-', ' ', $slug));
    }
}
