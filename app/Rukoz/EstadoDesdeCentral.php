<?php

namespace App\Rukoz;

use App\Enums\EstadoOrden;
use App\Models\Order;
use App\Services\DiscountCodeService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Rukoz\CoreClient\Eventos\PedidoActualizado;

/**
 * Refleja en el pedido de la web el estado que informa el Central (10.4). Es el listener del evento genérico
 * `PedidoActualizado` del paquete (se dispara con TODO cambio de estado de un pedido, ya con el estado completo y la regla de
 * `version` aplicada sobre `rukoz_pedidos`) y también lo usa `ConfirmacionDeVenta` con la respuesta de `confirmar`.
 *
 * Mapeo (`EstadoOrden::desdeCentral`): confirmado → Confirmado · empaquetado → Empaquetado · despachado → Despachado ·
 * retirado → Entregado · anulado → Anulado · cancelado (reserva liberada) → Cancelado. `reservado` / `requiere_revision` no
 * cambian el estado de la web.
 *
 * IDEMPOTENTE y EN ORDEN: un estado con `version` menor que la guardada en `orders.rukoz_version` se descarta; con la misma
 * se reaplica y deja todo igual (un evento repetido, o el que llega después de que `confirmar` ya devolvió ese estado). Corre
 * dentro de la transacción del paquete: solo escrituras locales, sin HTTP. Una referencia que la web no conoce se ignora.
 *
 * Una venta ANULADA o una reserva LIBERADA en el Central libera el uso del cupón (`liberarUso`, idempotente): la venta no se
 * hizo, el cupo vuelve. El stock lo devuelve el propio Central (evento `stock.actualizado`, que ya espeja `EspejoStock`).
 */
final class EstadoDesdeCentral
{
    public function __construct(private readonly DiscountCodeService $cupones) {}

    public function alActualizarse(PedidoActualizado $evento): void
    {
        $this->aplicar($evento->ref, $evento->version, $evento->estado, $evento->datos);
    }

    /**
     * @param  array<string, mixed>  $datos  El estado completo del pedido (como llega en los webhooks y en las respuestas).
     * @param  array<string, mixed>  $extra  Columnas propias de quien llama (por ejemplo `confirmado_por`), solo si se aplica.
     * @return Order|null El pedido actualizado; `null` si la web no lo conoce o la versión era vieja.
     */
    public function aplicar(string $ref, int $version, string $estadoCentral, array $datos, array $extra = []): ?Order
    {
        return DB::transaction(function () use ($ref, $version, $estadoCentral, $datos, $extra) {
            $pedido = Order::where('rukoz_ref', $ref)->lockForUpdate()->first();

            if ($pedido === null) {
                return null; // un pedido que esta web no conoce
            }

            if ($pedido->rukoz_version !== null && $version < $pedido->rukoz_version) {
                Log::info('Sistema Central: se descartó un estado viejo de un pedido.', ['pedido_id' => $pedido->id, 'version' => $version, 'guardada' => $pedido->rukoz_version]);

                return null;
            }

            $web = EstadoOrden::desdeCentral($estadoCentral);
            $cambios = ['rukoz_version' => $version] + self::cambiosDeRetencion($datos);

            if ($web === null) {
                // reservado / requiere_revision: el pedido sigue pendiente. Si ya es una venta no se toca (no se des-confirma).
                if (! $pedido->estaConfirmado()) {
                    $pedido->forceFill($cambios + ['rukoz_estado' => $estadoCentral])->save();
                }

                return $pedido;
            }

            // Un pedido que la web ya canceló es terminal: solo se acepta que el Central también lo haya cancelado.
            if ($pedido->estado === EstadoOrden::Cancelado && $web !== EstadoOrden::Cancelado) {
                Log::warning('Sistema Central: llegó un estado para un pedido cancelado en la web; se ignora.', ['pedido_id' => $pedido->id, 'estado' => $estadoCentral]);

                return null;
            }

            $cambios += match ($web) {
                EstadoOrden::Cancelado => $this->cambiosDeCancelacion($pedido, $datos),
                EstadoOrden::Anulado => $this->cambiosDeAnulacion($pedido, $datos),
                default => $this->cambiosDeVenta($pedido, $datos),
            };

            $pedido->forceFill($cambios + ['estado' => $web, 'rukoz_estado' => $estadoCentral] + $extra)->save();

            if (in_array($web, [EstadoOrden::Anulado, EstadoOrden::Cancelado], true)) {
                $this->cupones->liberarUso($pedido);
            }

            return $pedido;
        });
    }

    /**
     * `retenido_por_pago` que informa el Central en todo estado del pedido: la venta se confirmó con «despachar solo con pago
     * completo» y todavía queda saldo. Solo se guarda lo que viene.
     *
     * @return array<string, mixed>
     */
    private static function cambiosDeRetencion(array $datos): array
    {
        if (array_key_exists('retenido_por_pago', $datos) && $datos['retenido_por_pago'] !== null) {
            return ['rukoz_retenido_por_pago' => (bool) $datos['retenido_por_pago']];
        }

        return [];
    }

    /** @return array<string, mixed> */
    private function cambiosDeVenta(Order $pedido, array $datos): array
    {
        $tomar = fn (string $clave, mixed $actual) => array_key_exists($clave, $datos) ? $datos[$clave] : $actual;

        return [
            'confirmado_at' => self::fecha($datos['confirmado_at'] ?? null) ?? $pedido->confirmado_at ?? $pedido->confirmando_at ?? now(),
            'rukoz_numero_venta' => $tomar('numero_venta', $pedido->rukoz_numero_venta),
            'rukoz_total_venta' => $tomar('total', $pedido->rukoz_total_venta),
            'rukoz_pagado' => $tomar('pagado', $pedido->rukoz_pagado),
            'rukoz_saldo' => $tomar('saldo', $pedido->rukoz_saldo),
            'rukoz_transportista' => array_key_exists('transportista', $datos) ? ($datos['transportista']['nombre'] ?? null) : $pedido->rukoz_transportista,
            'rukoz_numero_seguimiento' => $tomar('numero_seguimiento', $pedido->rukoz_numero_seguimiento),
            'rukoz_url_seguimiento' => $tomar('url_seguimiento', $pedido->rukoz_url_seguimiento),
            // Las fechas de un estado vuelven a null al revertirlo: se reemplazan, no se mezclan.
            'rukoz_empaquetado_at' => array_key_exists('empaquetado_at', $datos) ? self::fecha($datos['empaquetado_at']) : $pedido->rukoz_empaquetado_at,
            'despachado_at' => array_key_exists('despachado_at', $datos) ? self::fecha($datos['despachado_at']) : $pedido->despachado_at,
            'rukoz_retirado_at' => array_key_exists('retirado_at', $datos) ? self::fecha($datos['retirado_at']) : $pedido->rukoz_retirado_at,
            'rukoz_anulado_at' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function cambiosDeAnulacion(Order $pedido, array $datos): array
    {
        return [
            'rukoz_anulado_at' => self::fecha($datos['anulado_at'] ?? null) ?? $pedido->rukoz_anulado_at ?? now(),
            'motivo_cancelacion' => self::motivo($datos['motivo'] ?? null) ?? $pedido->motivo_cancelacion,
            'rukoz_total_venta' => $datos['total'] ?? $pedido->rukoz_total_venta,
            'rukoz_pagado' => $datos['pagado'] ?? $pedido->rukoz_pagado,
            'rukoz_saldo' => $datos['saldo'] ?? $pedido->rukoz_saldo,
            'rukoz_numero_venta' => $datos['numero_venta'] ?? $pedido->rukoz_numero_venta,
        ];
    }

    /** @return array<string, mixed> */
    private function cambiosDeCancelacion(Order $pedido, array $datos): array
    {
        return [
            'motivo_cancelacion' => self::motivo($datos['motivo'] ?? $datos['motivo_cancelacion'] ?? null) ?? $pedido->motivo_cancelacion,
            'despachado_at' => null,
        ];
    }

    /** Fecha del Central (ISO 8601 con zona) en la zona de la base: Eloquent guarda el reloj tal cual, sin convertir. */
    public static function fecha(mixed $valor): ?Carbon
    {
        if (! is_string($valor) || trim($valor) === '') {
            return null;
        }

        try {
            return Carbon::parse($valor)->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    private static function motivo(mixed $valor): ?string
    {
        return is_string($valor) && trim($valor) !== '' ? mb_substr(trim($valor), 0, 500) : null;
    }
}
