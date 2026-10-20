<?php

namespace App\Rukoz;

use App\Enums\EstadoOrden;
use App\Models\Order;
use App\Services\DiscountCodeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Rukoz\CoreClient\Errores\AnularDesdeCentralException;
use Rukoz\CoreClient\Errores\IntegracionApagadaException;
use Rukoz\CoreClient\Errores\PedidoInexistenteException;
use Rukoz\CoreClient\Errores\RukozException;
use Rukoz\CoreClient\Facades\Rukoz;

/**
 * Cancelación de un pedido que tiene su stock reservado en el Central (`rukoz_ref`). La usan el admin
 * (`Admin\OrderController::updateStatus`) y la cancelación automática a los N días.
 *
 * Orden: primero el Central (HTTP, sin transacción ni locks tomados), después la transacción local (liberar el cupón de
 * forma idempotente + estado). Si el Central no responde —caído, timeout o circuito abierto— el paquete deja la
 * cancelación en su cola de compensaciones (`rukoz:compensaciones` la reintenta cada 5 minutos, hasta 24 horas) y el pedido
 * local se cancela igual, con `rukoz_estado = cancelacion_pendiente` hasta que se resuelva.
 *
 * No repone stock local: nunca se descontó (lo reservó el Central).
 */
final class ReservaDePedido
{
    public function __construct(private readonly DiscountCodeService $cupones) {}

    /**
     * @throws ValidationException Si el Central no deja cancelar (ya es una venta confirmada, integración apagada, rechazo).
     */
    public function cancelar(Order $pedido, string $motivo): void
    {
        $motivo = mb_substr(trim($motivo) !== '' ? trim($motivo) : 'Cancelado desde la web', 0, 500);
        $respuesta = ['estado' => Order::RESERVA_CANCELADA];

        try {
            $respuesta = Rukoz::cancelarReserva($pedido->rukoz_ref, $motivo);
        } catch (PedidoInexistenteException) {
            // El Central no conoce esta referencia: no hay reserva que liberar. Se cancela el pedido local y queda el aviso.
            Log::warning('Sistema Central: se canceló un pedido cuya reserva el Central no conoce.', ['order_id' => $pedido->id]);
        } catch (AnularDesdeCentralException) {
            throw ValidationException::withMessages([
                'estado' => 'Este pedido ya es una venta confirmada en el Sistema Central: se anula desde allá.',
            ]);
        } catch (IntegracionApagadaException $e) {
            throw ValidationException::withMessages(['estado' => ErroresDelCentral::paraElAdmin($e, 'cancelar la reserva')]);
        } catch (RukozException $e) {
            ErroresDelCentral::registrar($e, 'cancelar la reserva');

            throw ValidationException::withMessages(['estado' => ErroresDelCentral::paraElAdmin($e, 'cancelar la reserva')]);
        }

        DB::transaction(function () use ($pedido, $respuesta, $motivo) {
            // liberarUso() es idempotente (marca discount_usage_liberado_at): un reintento no libera dos veces.
            $this->cupones->liberarUso($pedido);

            $pedido->update([
                'estado' => EstadoOrden::Cancelado,
                'motivo_cancelacion' => $motivo,
                // null = el Central no respondió: la cancelación quedó en las compensaciones del paquete.
                'rukoz_estado' => $respuesta === null ? Order::RESERVA_CANCELACION_PENDIENTE : Order::RESERVA_CANCELADA,
                'rukoz_version' => isset($respuesta['version']) ? (int) $respuesta['version'] : $pedido->rukoz_version,
            ]);
        });
    }
}
