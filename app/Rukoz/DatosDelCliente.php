<?php

namespace App\Rukoz;

use App\Enums\EstadoOrden;
use App\Models\Order;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Rukoz\CoreClient\Errores\RukozException;
use Rukoz\CoreClient\Errores\SinStockException;
use Rukoz\CoreClient\Facades\Rukoz;

/**
 * Guarda los datos del cliente de un pedido (nombre, teléfono, DNI, provincia, localidad, código postal, email, observaciones) SIN
 * tocar las líneas: la tarjeta «Datos del cliente» de la ficha del pedido y el modal «Confirmar venta» (que los completa antes de
 * confirmar).
 *
 * Pedido de la web sola (sin `rukoz_ref`): se guarda y listo. Pedido con reserva en el Central: el Central tiene los datos del cliente
 * dentro del pedido, así que se manda el mismo `PUT /pedidos/{ref}` (`Rukoz::editarReserva`) con los datos nuevos y las líneas ACTUALES
 * del pedido. OJO: el PUT reemplaza el pedido entero y borra las líneas que no vienen, por eso siempre viajan todas, armadas por
 * `PedidoParaCentral` con lo que está guardado. El HTTP va antes de la transacción local; la versión que contesta el Central se
 * guarda en `rukoz_version`. Si el Central rechaza (o no responde) no se guarda nada; si la transacción local falla después de un PUT
 * correcto, el paquete vuelve a mandar el pedido anterior.
 */
final class DatosDelCliente
{
    public function __construct(private readonly PedidoParaCentral $armador) {}

    /** Por qué los datos de un pedido no se pueden editar (para la UI y el backend); null si se pueden. */
    public static function motivoDeSoloLectura(Order $pedido): ?string
    {
        return match (true) {
            $pedido->estado === EstadoOrden::Anulado => 'Esta venta fue anulada en el Sistema Central: el pedido es de solo lectura.',
            $pedido->estado === EstadoOrden::Cancelado => 'Un pedido cancelado no se puede editar.',
            $pedido->estaConfirmado() => 'Este pedido ya está confirmado en el Sistema Central y es de solo lectura. Para corregirlo, anulá la venta allá y rehacelo.',
            $pedido->usaCentral() && ! $pedido->tieneReservaAbierta() => 'Este pedido ya no tiene su reserva abierta en el Sistema Central: no se puede editar.',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $cambios  Solo las claves de DatosDeEnvio::EDITABLES; las que no vienen no se tocan.
     *
     * @throws ValidationException
     */
    public function actualizar(Order $pedido, array $cambios): Order
    {
        if (($motivo = self::motivoDeSoloLectura($pedido)) !== null) {
            throw ValidationException::withMessages(['estado' => $motivo]);
        }

        $atributos = $this->atributos($pedido, $cambios);

        if ($atributos === []) {
            return $pedido;
        }

        if (! $pedido->usaCentral()) {
            $pedido->forceFill($atributos)->save();

            return $pedido;
        }

        return $this->actualizarConReserva($pedido, $atributos);
    }

    /**
     * Columnas a guardar: solo las que de verdad cambian (si no cambia nada no se habla con el Central). «Nombre y apellido» es un solo
     * campo (`name`): al editarlo se junta lo que estuviera partido en `lastname`.
     *
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function atributos(Order $pedido, array $cambios): array
    {
        $atributos = [];

        foreach (Arr::only($cambios, DatosDeEnvio::EDITABLES) as $columna => $valor) {
            $valor = is_string($valor) ? trim($valor) : $valor;
            $valor = $valor === '' ? null : $valor;
            $actual = $columna === 'name' ? $pedido->nombreCompleto() : $pedido->{$columna};

            if (trim((string) $valor) !== trim((string) $actual)) {
                $atributos[$columna] = $valor;
            }
        }

        // El nombre no puede quedar vacío (la validación ya lo pide; esto es el seguro del servicio).
        if (array_key_exists('name', $atributos)) {
            if ($atributos['name'] === null) {
                throw ValidationException::withMessages(['name' => 'El nombre no puede quedar vacío.']);
            }

            $atributos['lastname'] = null;
        }

        return $atributos;
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function actualizarConReserva(Order $pedido, array $atributos): Order
    {
        // El pedido tal como está (lo que el Central tiene hoy): es a lo que se vuelve si la edición local falla.
        $actual = Order::with('items')->findOrFail($pedido->id);
        $anterior = $this->armador->armar($actual, $actual->items, $actual->created_at)->payload;

        // Cómo quedaría, en memoria. Si algo no se puede armar (un producto que ya no existe) falla acá, antes de hablar con el Central.
        $simulado = Order::with('items')->findOrFail($pedido->id)->forceFill($atributos);
        $plan = $this->armador->armar($simulado, $simulado->items, $simulado->created_at);

        try {
            $reserva = Rukoz::editarReserva($pedido->rukoz_ref, $plan->payload, function (array $central) use ($pedido, $atributos, $plan) {
                // Dentro de la transacción del paquete: solo base local, nada de HTTP.
                $bloqueado = Order::whereKey($pedido->id)->lockForUpdate()->firstOrFail();

                if (! $bloqueado->tieneReservaAbierta()) {
                    throw ValidationException::withMessages([
                        'estado' => 'El pedido cambió de estado mientras se guardaba: no se guardaron los datos.',
                    ]);
                }

                $bloqueado->forceFill($atributos + [
                    'rukoz_version' => $central['version'] ?? $bloqueado->rukoz_version,
                    'rukoz_estado' => $central['estado'] ?? $bloqueado->rukoz_estado,
                ])->save();

                // Lo que se guarda tiene que ser EXACTAMENTE lo que acepta el Central; si alguien tocó el pedido en el medio, se
                // aborta y el paquete vuelve a mandar el pedido anterior.
                $final = $this->armador->armar($bloqueado, $bloqueado->items()->get(), $bloqueado->created_at);

                if (json_encode($final->payload) !== json_encode($plan->payload)) {
                    throw ValidationException::withMessages([
                        'estado' => 'El pedido cambió mientras se guardaban los datos. Probá de nuevo.',
                    ]);
                }

                return $bloqueado;
            }, pedidoAnterior: $anterior, refLocal: (string) $pedido->id);
        } catch (SinStockException $e) {
            ErroresDelCentral::registrar($e, 'guardar los datos del cliente');

            throw ValidationException::withMessages([
                'estado' => collect(ErroresDelCentral::sinStock($e, $plan))->pluck('mensaje')->implode(' ').' No se guardaron los datos.',
            ]);
        } catch (RukozException $e) {
            ErroresDelCentral::registrar($e, 'guardar los datos del cliente');

            throw ValidationException::withMessages(['estado' => ErroresDelCentral::paraElAdmin($e, 'guardar los datos del cliente')]);
        }

        return $reserva->local;
    }
}
