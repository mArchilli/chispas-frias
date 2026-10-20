<?php

namespace Tests\Support;

use App\Rukoz\CatalogoDeChispasFrias;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Valida el body de `POST /pedidos` / `PUT /pedidos/{ref}` contra el contrato del Central 0.1.2 (con `nota` por ítem y
 * `punto`), sin llamarlo.
 *
 * Tres controles (mismo criterio que `ContratoCatalogoCentral`):
 *  1. Forma: ninguna clave que el contrato no conozca. Las permitidas salen del fixture oficial
 *     (`tests/fixtures/rukoz/request-pedido-crear.json`, copia de `sistema-rukoz/docs/api/ejemplos/` en la versión 0.1.2 del
 *     paquete).
 *  2. Reglas de campo: las de `App\Http\Requests\Api\PedidoWebRequest` del Central (tipos, largos, enums, mínimos).
 *  3. Contra el catálogo que la web le informó (`CatalogoDeChispasFrias`): cada línea es un ítem que existe y se puede pedir
 *     (un producto con variantes NUNCA va como `producto`), y las `selecciones` de un combo usan los grupos y las opciones
 *     del catálogo, con las cantidades que el combo pide por unidad.
 *
 * Devuelve la lista de errores (vacía = el Central lo aceptaría).
 */
class ContratoPedidoCentral
{
    /** @return list<string> */
    public static function errores(array $payload, bool $contraCatalogo = true): array
    {
        return [
            ...self::errorDeForma($payload),
            ...self::errorDeCampos($payload),
            ...($contraCatalogo ? self::errorContraCatalogo($payload) : []),
        ];
    }

    /** @return array<string, list<string>> */
    private static function clavesPermitidas(): array
    {
        $fixture = json_decode((string) file_get_contents(base_path('tests/fixtures/rukoz/request-pedido-crear.json')), true);
        $claves = ['pedido' => array_keys($fixture), 'cliente' => array_keys($fixture['cliente']), 'item' => [], 'seleccion' => [], 'opcion' => []];

        foreach ($fixture['items'] as $item) {
            $claves['item'] = array_values(array_unique([...$claves['item'], ...array_keys($item)]));

            foreach ($item['selecciones'] ?? [] as $seleccion) {
                $claves['seleccion'] = array_values(array_unique([...$claves['seleccion'], ...array_keys($seleccion)]));

                foreach ($seleccion['opciones'] as $opcion) {
                    $claves['opcion'] = array_values(array_unique([...$claves['opcion'], ...array_keys($opcion)]));
                }
            }
        }

        return $claves;
    }

    /**
     * `punto`, si viaja, tiene que ser uno de los de `GET /referencias` (el Central responde 422 PUNTO_DESCONOCIDO si no). Esta
     * web no manda `punto` (tiene uno solo), pero el contrato lo admite y el validador de `confirmar` lo comprueba igual.
     *
     * @param  array<string, mixed>  $referencias
     * @return list<string>
     */
    public static function errorDePunto(array $payload, array $referencias): array
    {
        $punto = $payload['punto'] ?? null;
        $validos = array_column($referencias['puntos'] ?? [], 'codigo');

        return $punto !== null && ! in_array($punto, $validos, true)
            ? ["punto: «{$punto}» no es un punto de la integración (válidos: ".implode(', ', $validos).')']
            : [];
    }

    /** @return list<string> */
    private static function errorDeForma(array $payload): array
    {
        $permitidas = self::clavesPermitidas();
        $errores = [];
        $revisar = function (string $tipo, mixed $fila, string $donde) use ($permitidas, &$errores) {
            if (! is_array($fila)) {
                $errores[] = "{$donde}: tiene que ser un objeto";

                return;
            }

            foreach (array_diff(array_keys($fila), $permitidas[$tipo]) as $extra) {
                $errores[] = "{$donde}: la clave «{$extra}» no existe en el contrato";
            }
        };

        $revisar('pedido', $payload, 'pedido');
        $revisar('cliente', $payload['cliente'] ?? [], 'cliente');

        foreach ($payload['items'] ?? [] as $i => $item) {
            $revisar('item', $item, "items.{$i}");

            foreach ($item['selecciones'] ?? [] as $j => $seleccion) {
                $revisar('seleccion', $seleccion, "items.{$i}.selecciones.{$j}");

                foreach ($seleccion['opciones'] ?? [] as $k => $opcion) {
                    $revisar('opcion', $opcion, "items.{$i}.selecciones.{$j}.opciones.{$k}");
                }
            }
        }

        return $errores;
    }

    /** Las reglas de `PedidoWebRequest`. @return list<string> */
    private static function errorDeCampos(array $payload): array
    {
        $validador = Validator::make($payload, [
            'external_ref' => ['required', 'string', 'max:100', 'not_regex:/\//'],
            'cliente' => ['required', 'array'],
            'cliente.nombre' => ['required', 'string', 'max:255'],
            'cliente.telefono' => ['nullable', 'string', 'max:60'],
            'cliente.dni' => ['nullable', 'string', 'max:30'],
            'cliente.cp' => ['nullable', 'string', 'max:10'],
            'cliente.email' => ['nullable', 'string', 'max:255'],
            'cliente.direccion' => ['nullable', 'string', 'max:500'],
            'cliente.localidad' => ['nullable', 'string', 'max:255'],
            'cliente.provincia' => ['nullable', 'string', 'max:100'],
            'cliente.observaciones' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.tipo' => ['required', 'string', Rule::in(['producto', 'variante', 'combo'])],
            'items.*.id' => ['required', 'string', 'max:100'],
            'items.*.nombre' => ['nullable', 'string', 'max:255'],
            'items.*.cantidad' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.precio_unitario' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'items.*.nota' => ['nullable', 'string', 'max:500'],
            'items.*.selecciones' => ['nullable', 'array', 'max:50'],
            'items.*.selecciones.*.grupo' => ['required', 'string', 'max:255'],
            'items.*.selecciones.*.opciones' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.selecciones.*.opciones.*.tipo' => ['required', Rule::in(['producto', 'variante'])],
            'items.*.selecciones.*.opciones.*.id' => ['required', 'string', 'max:100'],
            'items.*.selecciones.*.opciones.*.cantidad' => ['nullable', 'integer', 'min:1'],
            'total' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'total_con_recargo' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'moneda' => ['nullable', 'string', 'size:3'],
            'creado_at' => ['nullable', 'date'],
            'punto' => ['nullable', 'string', 'max:30'],
        ]);

        $errores = $validador->errors()->all();

        foreach ($payload['items'] ?? [] as $i => $item) {
            if (($item['tipo'] ?? null) !== 'combo' && isset($item['selecciones'])) {
                $errores[] = "items.{$i}: solo un combo lleva selecciones";
            }

            if (isset($item['nota']) && trim((string) $item['nota']) === '') {
                $errores[] = "items.{$i}.nota: una nota vacía no se manda";
            }
        }

        return $errores;
    }

    /** @return list<string> */
    private static function errorContraCatalogo(array $payload): array
    {
        $catalogo = new CatalogoDeChispasFrias;
        $productos = collect(iterator_to_array($catalogo->productos(), false))->keyBy('id');
        $variantes = $productos->flatMap(fn ($p) => collect($p['variantes'] ?? [])->map(fn ($v) => $v + ['producto' => $p['id']]))->keyBy('id');
        $combos = collect(iterator_to_array($catalogo->combos(), false))->keyBy('id');
        $errores = [];

        foreach ($payload['items'] ?? [] as $i => $item) {
            $tipo = $item['tipo'] ?? null;
            $id = (string) ($item['id'] ?? '');

            if ($tipo === 'producto') {
                $producto = $productos->get($id);

                if ($producto === null) {
                    $errores[] = "items.{$i}: el producto {$id} no está en el catálogo";
                } elseif (! empty($producto['variantes'])) {
                    $errores[] = "items.{$i}: el producto {$id} tiene variantes; se pide por variante (ITEM_NO_VINCULADO)";
                }
            } elseif ($tipo === 'variante') {
                if (! $variantes->has($id)) {
                    $errores[] = "items.{$i}: la variante {$id} no está en el catálogo";
                }
            } elseif ($tipo === 'combo') {
                $errores = [...$errores, ...self::errorDeCombo($i, $item, $combos->get($id))];
            }
        }

        return $errores;
    }

    /** @return list<string> */
    private static function errorDeCombo(int $i, array $item, ?array $combo): array
    {
        if ($combo === null) {
            return ["items.{$i}: el combo {$item['id']} no está en el catálogo"];
        }

        if ($combo['tipo'] === 'fijo') {
            return isset($item['selecciones']) ? ["items.{$i}: el combo {$item['id']} es fijo y no lleva selecciones (SELECCION_INVALIDA)"] : [];
        }

        $errores = [];
        $grupos = collect($combo['grupos'])->keyBy('nombre');
        $selecciones = collect($item['selecciones'] ?? []);

        if ($selecciones->count() !== $grupos->count()) {
            $errores[] = "items.{$i}: el combo tiene {$grupos->count()} grupos y se mandaron {$selecciones->count()} selecciones";
        }

        if ($selecciones->pluck('grupo')->duplicates()->isNotEmpty()) {
            $errores[] = "items.{$i}: hay grupos repetidos en las selecciones";
        }

        foreach ($selecciones as $j => $seleccion) {
            $grupo = $grupos->get($seleccion['grupo'] ?? '');

            if ($grupo === null) {
                $errores[] = "items.{$i}.selecciones.{$j}: el grupo «".($seleccion['grupo'] ?? '').'» no existe en el catálogo del combo';

                continue;
            }

            $permitidas = collect($grupo['opciones'])->map(fn ($o) => $o['tipo'].':'.$o['id']);
            $suma = 0;

            foreach ($seleccion['opciones'] as $opcion) {
                $suma += (int) ($opcion['cantidad'] ?? 1);

                if (! $permitidas->contains($opcion['tipo'].':'.$opcion['id'])) {
                    $errores[] = "items.{$i}.selecciones.{$j}: la opción {$opcion['tipo']}:{$opcion['id']} no es del grupo";
                }
            }

            if ($suma !== (int) $grupo['cantidad']) {
                $errores[] = "items.{$i}.selecciones.{$j}: las cantidades suman {$suma} y el grupo pide {$grupo['cantidad']}";
            }
        }

        return $errores;
    }
}
