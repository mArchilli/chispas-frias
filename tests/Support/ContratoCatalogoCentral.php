<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Valida un request de `POST /catalogo/sync` contra el contrato del Central, sin llamarlo.
 *
 * Tres controles:
 *  1. Forma: ningún elemento trae claves que el contrato no conoce. Las permitidas salen del fixture
 *     oficial del Central (`tests/fixtures/rukoz/request-catalogo-sync.json`, copia de
 *     `sistema-rukoz/docs/api/ejemplos/`), así que si el ejemplo del Central cambia y se vuelve a copiar,
 *     el test lo nota.
 *  2. Reglas de campo: las mismas que `App\Http\Requests\Api\CatalogoSyncRequest` del Central
 *     (y `components/schemas/Catalogo*` de su openapi.yaml): tipos, largos, enums, mínimos.
 *  3. Reglas cruzadas: ids repetidos, ítems repetidos dentro de un combo, nombres de grupo repetidos,
 *     opciones repetidas y que todo lo que un combo referencia exista en el mismo request.
 *
 * Devuelve la lista de errores (vacía = el Central lo aceptaría).
 */
class ContratoCatalogoCentral
{
    /** @return list<string> */
    public static function errores(array $payload): array
    {
        return [...self::errorDeForma($payload), ...self::errorDeCampos($payload), ...self::errorCruzado($payload)];
    }

    /** @return array<string, list<string>> claves permitidas por tipo de elemento, según el fixture del Central */
    private static function clavesPermitidas(): array
    {
        $fixture = json_decode((string) file_get_contents(base_path('tests/fixtures/rukoz/request-catalogo-sync.json')), true);
        $claves = ['producto' => [], 'variante' => [], 'combo' => [], 'item' => [], 'grupo' => [], 'opcion' => []];
        $sumar = function (string $tipo, array $fila) use (&$claves) {
            $claves[$tipo] = array_values(array_unique([...$claves[$tipo], ...array_keys($fila)]));
        };

        foreach ($fixture['productos'] as $producto) {
            $sumar('producto', $producto);

            foreach ($producto['variantes'] ?? [] as $variante) {
                $sumar('variante', $variante);
            }
        }

        foreach ($fixture['combos'] as $combo) {
            $sumar('combo', $combo);

            foreach ($combo['items'] ?? [] as $item) {
                $sumar('item', $item);
            }

            foreach ($combo['grupos'] ?? [] as $grupo) {
                $sumar('grupo', $grupo);

                foreach ($grupo['opciones'] as $opcion) {
                    $sumar('opcion', $opcion);
                }
            }
        }

        return $claves;
    }

    /** @return list<string> */
    private static function errorDeForma(array $payload): array
    {
        $permitidas = self::clavesPermitidas();
        $errores = [];
        $revisar = function (string $tipo, array $fila, string $donde) use ($permitidas, &$errores) {
            foreach (array_diff(array_keys($fila), $permitidas[$tipo]) as $extra) {
                $errores[] = "{$donde}: la clave «{$extra}» no existe en el contrato";
            }
        };

        foreach ($payload['productos'] ?? [] as $i => $producto) {
            $revisar('producto', $producto, "productos.{$i}");

            foreach ($producto['variantes'] ?? [] as $j => $variante) {
                $revisar('variante', $variante, "productos.{$i}.variantes.{$j}");
            }
        }

        foreach ($payload['combos'] ?? [] as $i => $combo) {
            $revisar('combo', $combo, "combos.{$i}");

            foreach ($combo['items'] ?? [] as $j => $item) {
                $revisar('item', $item, "combos.{$i}.items.{$j}");
            }

            foreach ($combo['grupos'] ?? [] as $g => $grupo) {
                $revisar('grupo', $grupo, "combos.{$i}.grupos.{$g}");

                foreach ($grupo['opciones'] ?? [] as $o => $opcion) {
                    $revisar('opcion', $opcion, "combos.{$i}.grupos.{$g}.opciones.{$o}");
                }
            }
        }

        return $errores;
    }

    /** @return list<string> */
    private static function errorDeCampos(array $payload): array
    {
        $item = fn (string $p) => [
            "{$p}.id" => ['required', 'string', 'max:100'],
            "{$p}.nombre" => ['required', 'string', 'max:255'],
            "{$p}.precio" => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            "{$p}.activo" => ['sometimes', 'boolean'],
        ];

        $reglas = [
            'completo' => ['required', 'boolean'],
            'productos' => ['sometimes', 'nullable', 'array'],
            'productos.*' => ['array'],
            ...$item('productos.*'),
            'productos.*.sku' => ['nullable', 'string', 'max:100'],
            'productos.*.variantes' => ['sometimes', 'nullable', 'array'],
            'productos.*.variantes.*' => ['array'],
            ...$item('productos.*.variantes.*'),
            'productos.*.variantes.*.sku' => ['nullable', 'string', 'max:100'],
            'combos' => ['sometimes', 'nullable', 'array'],
            'combos.*' => ['array'],
            ...$item('combos.*'),
            'combos.*.tipo' => ['required', Rule::in(['fijo', 'seleccion'])],
            'combos.*.items' => ['sometimes', 'nullable', 'array'],
            'combos.*.items.*' => ['array'],
            'combos.*.items.*.tipo' => ['required', Rule::in(['producto', 'variante'])],
            'combos.*.items.*.id' => ['required', 'string', 'max:100'],
            'combos.*.items.*.cantidad' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'combos.*.grupos' => ['sometimes', 'nullable', 'array'],
            'combos.*.grupos.*' => ['array'],
            'combos.*.grupos.*.nombre' => ['required', 'string', 'max:255'],
            'combos.*.grupos.*.cantidad' => ['required', 'integer', 'min:1', 'max:100000'],
            'combos.*.grupos.*.opciones' => ['required', 'array', 'min:1'],
            'combos.*.grupos.*.opciones.*' => ['array'],
            'combos.*.grupos.*.opciones.*.tipo' => ['required', Rule::in(['producto', 'variante'])],
            'combos.*.grupos.*.opciones.*.id' => ['required', 'string', 'max:100'],
        ];

        $validador = Validator::make($payload, $reglas);

        return $validador->fails() ? array_map(fn ($e) => (string) $e, $validador->errors()->all()) : [];
    }

    /** @return list<string> */
    private static function errorCruzado(array $payload): array
    {
        $errores = [];
        $presentes = [];

        foreach ($payload['productos'] ?? [] as $producto) {
            self::registrar($errores, $presentes, 'producto', (string) $producto['id']);

            foreach ($producto['variantes'] ?? [] as $variante) {
                self::registrar($errores, $presentes, 'variante', (string) $variante['id']);
            }
        }

        $combosVistos = [];

        foreach ($payload['combos'] ?? [] as $combo) {
            $id = (string) $combo['id'];

            if (isset($combosVistos[$id])) {
                $errores[] = "El combo «{$id}» está repetido";
            }

            $combosVistos[$id] = true;
            $referencias = [];

            if ($combo['tipo'] === 'fijo') {
                if (empty($combo['items'])) {
                    $errores[] = "El combo fijo «{$id}» necesita al menos un ítem";
                }

                $vistos = [];

                foreach ($combo['items'] ?? [] as $item) {
                    $clave = $item['tipo'].':'.$item['id'];

                    if (isset($vistos[$clave])) {
                        $errores[] = "Combo «{$id}»: el {$item['tipo']} «{$item['id']}» está repetido (hay que sumar la cantidad)";
                    }

                    $vistos[$clave] = true;
                    $referencias[] = [$item['tipo'], (string) $item['id']];
                }
            } else {
                if (empty($combo['grupos'])) {
                    $errores[] = "El combo de selección «{$id}» necesita al menos un grupo";
                }

                $nombres = [];

                foreach ($combo['grupos'] ?? [] as $grupo) {
                    $nombre = trim($grupo['nombre']);

                    if ($nombre === '' || isset($nombres[$nombre])) {
                        $errores[] = "Combo «{$id}»: nombre de grupo vacío o repetido («{$nombre}»)";
                    }

                    $nombres[$nombre] = true;
                    $vistas = [];

                    foreach ($grupo['opciones'] as $opcion) {
                        $clave = $opcion['tipo'].':'.$opcion['id'];

                        if (isset($vistas[$clave])) {
                            $errores[] = "Combo «{$id}», grupo «{$nombre}»: la opción {$clave} está repetida";
                        }

                        $vistas[$clave] = true;
                        $referencias[] = [$opcion['tipo'], (string) $opcion['id']];
                    }
                }
            }

            foreach ($referencias as [$tipo, $refId]) {
                if (! isset($presentes[$tipo.':'.$refId])) {
                    $errores[] = "Combo «{$id}» referencia el {$tipo} «{$refId}», que no viene en el request";
                }
            }
        }

        return $errores;
    }

    /**
     * @param  list<string>  $errores
     * @param  array<string, true>  $presentes
     */
    private static function registrar(array &$errores, array &$presentes, string $tipo, string $id): void
    {
        if (isset($presentes["{$tipo}:{$id}"])) {
            $errores[] = "El {$tipo} «{$id}» está repetido";
        }

        $presentes["{$tipo}:{$id}"] = true;
    }
}
