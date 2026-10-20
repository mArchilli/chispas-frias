<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Validator;

/**
 * Valida el body de `POST /pedidos/{ref}/confirmar` contra el contrato del Central 0.1.2 (con `punto` y `solo_con_pago_completo`), sin llamarlo (mismo criterio que
 * `ContratoPedidoCentral`).
 *
 *  1. Forma: ninguna clave que el contrato no conozca. Las permitidas salen del fixture oficial
 *     (`tests/fixtures/rukoz/request-pedido-confirmar.json`, copia de `sistema-rukoz/docs/api/ejemplos/`).
 *  2. Reglas de campo: las de `App\Http\Requests\Api\ConfirmarPedidoWebRequest` del Central (tipos, largos, `gt:0`, máximos).
 *  3. Reglas de negocio que el Central aplica al confirmar y que la web tiene que respetar: `descuento` NUNCA (el del cupón ya
 *     viaja en el `total` del pedido), un retiro no lleva costo ni transportista, un envío gratis no lleva costo, los pagos no
 *     superan el total de la venta, y la cuenta y la forma de envío existen en `GET /referencias`.
 *
 * Devuelve la lista de errores (vacía = el Central lo aceptaría).
 */
class ContratoConfirmacionCentral
{
    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>|null  $referencias  La respuesta de `GET /referencias` contra la que se comprueban cuentas y formas de envío.
     * @param  float|null  $totalDeLaVenta  Productos − cupón + envío cobrado, para exigir que los pagos no lo superen.
     * @return list<string>
     */
    public static function errores(array $body, ?array $referencias = null, ?float $totalDeLaVenta = null): array
    {
        return [
            ...self::errorDeForma($body),
            ...self::errorDeCampos($body),
            ...self::errorDeNegocio($body, $referencias, $totalDeLaVenta),
        ];
    }

    /** @return list<string> */
    private static function errorDeForma(array $body): array
    {
        $fixture = json_decode((string) file_get_contents(base_path('tests/fixtures/rukoz/request-pedido-confirmar.json')), true);
        $errores = [];

        $revisar = function (array $permitidas, mixed $fila, string $donde) use (&$errores) {
            if (! is_array($fila)) {
                $errores[] = "{$donde}: tiene que ser un objeto";

                return;
            }

            foreach (array_diff(array_keys($fila), $permitidas) as $extra) {
                $errores[] = "{$donde}: la clave «{$extra}» no existe en el contrato";
            }
        };

        $revisar(array_keys($fixture), $body, 'confirmar');
        $revisar(array_keys($fixture['vendedor']), $body['vendedor'] ?? [], 'vendedor');
        $revisar(array_keys($fixture['envio']), $body['envio'] ?? [], 'envio');

        foreach ($body['pagos'] ?? [] as $i => $pago) {
            $revisar(array_keys($fixture['pagos'][0]), $pago, "pagos.{$i}");
        }

        return $errores;
    }

    /** @return list<string> */
    private static function errorDeCampos(array $body): array
    {
        $validador = Validator::make($body, [
            'vendedor' => ['required', 'array'],
            'vendedor.id' => ['required', 'string', 'max:100'],
            'vendedor.email' => ['nullable', 'string', 'max:255'],
            'vendedor.nombre' => ['nullable', 'string', 'max:255'],
            'pagos' => ['nullable', 'array', 'max:20'],
            'pagos.*.cuenta' => ['required', 'string', 'max:60'],
            'pagos.*.medio' => ['nullable', 'string', 'max:30'],
            'pagos.*.monto' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'pagos.*.fecha' => ['nullable', 'date'],
            'envio' => ['required', 'array'],
            'envio.forma_envio_id' => ['required', 'integer'],
            'envio.costo_cobrado' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'envio.es_gratis' => ['nullable', 'boolean'],
            'envio.transportista_id' => ['nullable', 'integer'],
            'descuento' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'recargo_medio_pago' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'confirmado_at' => ['nullable', 'date'],
            'punto' => ['nullable', 'string', 'max:30'],
            'solo_con_pago_completo' => ['nullable', 'boolean'],
        ]);

        return $validador->fails() ? collect($validador->errors()->all())->values()->all() : [];
    }

    /** @return list<string> */
    private static function errorDeNegocio(array $body, ?array $referencias, ?float $totalDeLaVenta): array
    {
        $errores = [];

        if (array_key_exists('descuento', $body)) {
            $errores[] = 'descuento: la web no lo manda (el del cupón ya viaja en el total del pedido y se restaría dos veces)';
        }

        $envio = $body['envio'] ?? [];

        if (($envio['es_gratis'] ?? false) === true && (float) ($envio['costo_cobrado'] ?? 0) > 0) {
            $errores[] = 'envio: un envío gratis no puede tener costo cobrado';
        }

        if ($totalDeLaVenta !== null) {
            $pagado = array_sum(array_map(fn ($p) => (float) ($p['monto'] ?? 0), $body['pagos'] ?? []));

            if ($pagado > $totalDeLaVenta + 0.004) {
                $errores[] = "pagos: suman {$pagado} y la venta {$totalDeLaVenta} (nunca más que el total)";
            }
        }

        if ($referencias !== null) {
            $errores = [...$errores, ...ContratoPedidoCentral::errorDePunto($body, $referencias)];

            $cuentas = array_column($referencias['cuentas'] ?? [], 'codigo');

            foreach ($body['pagos'] ?? [] as $i => $pago) {
                if (! in_array($pago['cuenta'] ?? null, $cuentas, true)) {
                    $errores[] = "pagos.{$i}.cuenta: no existe la cuenta «".($pago['cuenta'] ?? '').'» para esta empresa';
                }
            }

            $forma = collect($referencias['formas_envio'] ?? [])->firstWhere('id', $envio['forma_envio_id'] ?? null);

            if ($forma === null) {
                $errores[] = 'envio.forma_envio_id: no existe esa forma de envío';
            } elseif (($forma['es_retiro'] ?? false) === true) {
                if ((float) ($envio['costo_cobrado'] ?? 0) > 0 || ($envio['es_gratis'] ?? false) === true || ! empty($envio['transportista_id'])) {
                    $errores[] = 'envio: un retiro/entrega en mano no lleva costo, envío gratis ni transportista';
                }
            }

            if (! empty($envio['transportista_id']) && ! in_array($envio['transportista_id'], array_column($referencias['transportistas'] ?? [], 'id'), true)) {
                $errores[] = 'envio.transportista_id: no existe ese transportista';
            }
        }

        return $errores;
    }
}
