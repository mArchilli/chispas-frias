<?php

namespace App\Rukoz;

use App\Models\Order;
use App\Models\User;
use App\Support\ZonaHoraria;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Rukoz\CoreClient\Errores\CentralNoDisponibleException;
use Rukoz\CoreClient\Errores\IntegracionApagadaException;
use Rukoz\CoreClient\Errores\PedidoCanceladoException;
use Rukoz\CoreClient\Errores\PedidoRequiereRevisionException;
use Rukoz\CoreClient\Errores\PedidoYaConfirmadoException;
use Rukoz\CoreClient\Errores\RukozException;
use Rukoz\CoreClient\Errores\ValidacionException as ValidacionDelCentral;
use Rukoz\CoreClient\Errores\VendedorNoMapeadoException;
use Rukoz\CoreClient\Facades\Rukoz;
use Throwable;

/**
 * Confirmar la venta de un pedido reservado en el Central (10.4): `Rukoz::confirmar` → `POST /pedidos/{ref}/confirmar`.
 *
 * Qué se manda: `vendedor` (adaptador `VendedorParaCentral`), `pagos[]` (0 a 20: cuenta, medio, monto, fecha), `envio`
 * (`forma_envio_id`, `costo_cobrado`, `es_gratis`, `transportista_id`), `confirmado_at` y `solo_con_pago_completo` (el vendedor pide
 * que no se despache hasta el pago completo). NO se manda `punto` (esta web tiene uno solo: rige el predeterminado de la
 * integración) ni `descuento`: el del cupón ya viaja en el `total` del pedido (10.3) y mandarlo otra vez se restaría dos veces. El envío no entra en el `total` del pedido:
 * va solo acá, y la venta queda en `productos − cupón + envío cobrado`.
 *
 * IDEMPOTENCIA. (1) Un lock por pedido serializa el doble clic: el segundo ve el pedido ya confirmado y no llama al Central.
 * (2) `confirmando_at` se fija UNA vez, antes del primer intento, y es la `fecha` de los pagos y el `confirmado_at`: un reintento
 * tras un timeout lleva exactamente los mismos bytes, el paquete reutiliza la `Idempotency-Key` y el Central devuelve la misma
 * venta (200). Si el Central ya tenía la venta y la respuesta se perdió, el reintento la recupera.
 *
 * NADA A MEDIAS. Si el Central rechaza o no responde, el pedido local no cambia (solo queda fijada `confirmando_at`). Si el Central
 * confirma y falla el guardado local, la venta existe allá y el estado llega igual por `pedido.confirmado` (o `rukoz:revisar-pedidos
 * --reconciliar`); un nuevo clic recupera la misma venta.
 */
final class ConfirmacionDeVenta
{
    public function __construct(private readonly EstadoDesdeCentral $estados) {}

    /** ¿Está encendida la confirmación? Necesita `INTEGRACION_CONFIRMACION` y la integración (`RUKOZ_INTEGRACION`). */
    public static function activa(): bool
    {
        return (bool) config('integracion.confirmacion') && (bool) config('rukoz.habilitada');
    }

    /**
     * Total de la venta: productos − cupón (el `total` del pedido) + envío cobrado. Es lo que no pueden superar los pagos.
     */
    public static function totalDeLaVenta(Order $pedido, float $costoEnvio): float
    {
        return round((float) $pedido->total + $costoEnvio, 2);
    }

    /**
     * @param  array{pagos?: list<array{cuenta: string, medio?: string|null, monto: float|int|string}>, envio: array{forma_envio_id: int, costo_cobrado?: float|int|string|null, es_gratis?: bool, transportista_id?: int|null}}  $datos
     * @return array{pedido: Order, ya_confirmado: bool}
     *
     * @throws ValidationException Con la clave `confirmacion`: explica por qué no se pudo (el pedido no cambió).
     */
    public function confirmar(Order $pedido, User $vendedor, array $datos, ?User $confirmadoPor = null): array
    {
        $lock = Cache::lock('rukoz:confirmar:'.$pedido->id, 90);

        if (! $lock->get()) {
            throw $this->error('Este pedido se está confirmando en este momento. Esperá unos segundos y revisá su estado antes de reintentar.');
        }

        try {
            // Se relee con el lock tomado: un doble clic llega acá con el pedido ya confirmado por el primero.
            $pedido = Order::findOrFail($pedido->id);

            if ($pedido->estaConfirmado()) {
                return ['pedido' => $pedido, 'ya_confirmado' => true];
            }

            $this->exigirConfirmable($pedido);

            $payload = $this->armarPayload($pedido, $vendedor, $datos);

            return ['pedido' => $this->enviar($pedido, $payload, $datos, $confirmadoPor), 'ya_confirmado' => false];
        } finally {
            $lock->release();
        }
    }

    /** @throws ValidationException */
    private function exigirConfirmable(Order $pedido): void
    {
        if (! self::activa()) {
            throw $this->error('La confirmación de ventas con el Sistema Central está apagada.');
        }

        if (! $pedido->usaCentral()) {
            throw $this->error('Este pedido no tiene una reserva en el Sistema Central: se gestiona como siempre.');
        }

        if ($pedido->rukoz_estado === 'requiere_revision') {
            throw $this->error('Este pedido tiene productos sin vincular en el Sistema Central y todavía no se puede confirmar. Avisá a administración.');
        }

        if (! $pedido->puedeConfirmarse()) {
            throw $this->error("Este pedido no se puede confirmar en su estado actual ({$pedido->estado->label()}).");
        }
    }

    /**
     * El body de `confirmar`, con los bytes estables entre reintentos (ver la nota de la clase).
     *
     * @return array<string, mixed>
     */
    public function armarPayload(Order $pedido, User $vendedor, array $datos): array
    {
        // Primero lo que puede fallar sin tocar nada: sin mapeo no se fija ni la hora del intento.
        $vendedorDelPayload = VendedorParaCentral::payload($vendedor);

        $envio = $datos['envio'];
        $esGratis = (bool) ($envio['es_gratis'] ?? false);
        $costo = $esGratis ? 0.0 : round((float) ($envio['costo_cobrado'] ?? 0), 2);
        $pagos = array_values($datos['pagos'] ?? []);

        $suma = round(array_sum(array_map(fn ($p) => (float) $p['monto'], $pagos)), 2);
        $total = self::totalDeLaVenta($pedido, $costo);

        if ($suma > $total + 0.004) {
            throw ValidationException::withMessages([
                'pagos' => 'Los pagos ('.self::plata($suma).') suman más que el total de la venta ('.self::plata($total).').',
            ]);
        }

        $fecha = ZonaHoraria::aLocal($this->fijarMomento($pedido))->toIso8601String();

        return array_filter([
            'vendedor' => $vendedorDelPayload,
            'pagos' => array_map(fn (array $pago) => array_filter([
                'cuenta' => (string) $pago['cuenta'],
                'medio' => ($pago['medio'] ?? null) !== null && $pago['medio'] !== '' ? (string) $pago['medio'] : null,
                'monto' => self::numero((float) $pago['monto']),
                'fecha' => $fecha,
            ], fn ($v) => $v !== null), $pagos),
            'envio' => array_filter([
                'forma_envio_id' => (int) $envio['forma_envio_id'],
                'costo_cobrado' => self::numero($costo),
                'es_gratis' => $esGratis,
                'transportista_id' => isset($envio['transportista_id']) && $envio['transportista_id'] !== '' ? (int) $envio['transportista_id'] : null,
            ], fn ($v) => $v !== null),
            // Solo tiene sentido con saldo: si los pagos cubren la venta el Central no retiene nada.
            'solo_con_pago_completo' => (bool) ($datos['solo_con_pago_completo'] ?? false) && $suma < $total - 0.004,
            'confirmado_at' => $fecha,
        ], fn ($v) => $v !== null);
    }

    /** `confirmando_at`: se fija una sola vez (aunque dos procesos lo intenten) y de ahí sale la hora de todos los reintentos. */
    private function fijarMomento(Order $pedido): \Carbon\CarbonInterface
    {
        if ($pedido->confirmando_at === null) {
            Order::whereKey($pedido->id)->whereNull('confirmando_at')->update(['confirmando_at' => now()->startOfSecond()]);
            $pedido->refresh();
        }

        return $pedido->confirmando_at;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $datos
     */
    private function enviar(Order $pedido, array $payload, array $datos, ?User $confirmadoPor): Order
    {
        try {
            $respuesta = Rukoz::confirmar($pedido->rukoz_ref, $payload, refLocal: (string) $pedido->id);
        } catch (VendedorNoMapeadoException) {
            throw $this->error('El vendedor todavía no está habilitado en el Sistema Central; ya se avisó a administración. Cuando lo habiliten, volvé a confirmar.');
        } catch (PedidoRequiereRevisionException) {
            throw $this->error('Faltan vínculos de productos en el Sistema Central para confirmar este pedido; ya se avisó a administración. Se vuelve a revisar solo.');
        } catch (PedidoYaConfirmadoException|PedidoCanceladoException $e) {
            // El Central ya lo tiene confirmado (con otro contenido) o cancelado: se trae su estado real y se muestra.
            $this->reconciliar($pedido);

            throw $this->error($e instanceof PedidoCanceladoException
                ? 'Este pedido está cancelado en el Sistema Central: no se puede confirmar. Se actualizó su estado.'
                : 'Este pedido ya estaba confirmado en el Sistema Central (con otros datos de pago o envío). Se actualizó su estado: revisá la venta allá.');
        } catch (ValidacionDelCentral $e) {
            throw $this->error('El Sistema Central no aceptó los datos: '.$this->detalle($e).'.');
        } catch (CentralNoDisponibleException) {
            throw $this->error('No pudimos comunicarnos con el Sistema Central, así que no se confirmó la venta y el pedido no cambió. Probá de nuevo en unos minutos (si la venta ya se había creado, se recupera sola).');
        } catch (IntegracionApagadaException $e) {
            throw $this->error(ErroresDelCentral::paraElAdmin($e, 'confirmar la venta'));
        } catch (RukozException $e) {
            ErroresDelCentral::registrar($e, 'confirmar la venta');

            throw $this->error('El Sistema Central rechazó la confirmación: '.$this->mensajeDelCentral($e).' El pedido no cambió.');
        }

        try {
            $guardado = $this->estados->aplicar(
                $pedido->rukoz_ref,
                (int) ($respuesta['version'] ?? $pedido->rukoz_version ?? 0),
                (string) ($respuesta['estado'] ?? 'confirmado'),
                $respuesta,
                [
                    'confirmado_por' => $confirmadoPor?->id,
                    'envio_costo_cobrado' => ($payload['envio']['costo_cobrado'] ?? 0),
                ],
            );
        } catch (Throwable $e) {
            Log::critical('Sistema Central: la venta se confirmó pero no se pudo guardar el estado local.', ['pedido_id' => $pedido->id, 'error' => $e::class]);

            throw $this->error('La venta se confirmó en el Sistema Central pero no pudimos guardar el resultado acá. Se va a actualizar sola en unos minutos; no la confirmes de nuevo.');
        }

        return $guardado ?? $pedido->refresh();
    }

    private function reconciliar(Order $pedido): void
    {
        try {
            Rukoz::actualizarPedidoDesdeCentral($pedido->rukoz_ref);
        } catch (RukozException $e) {
            ErroresDelCentral::registrar($e, 'reconciliar un pedido al confirmar');
        }
    }

    /** Los mensajes por campo de un `VALIDACION` (`pagos.0.cuenta`, `envio.forma_envio_id`…); son del Central, sin datos del cliente. */
    private function detalle(RukozException $e): string
    {
        $mensajes = [];

        foreach ($e->detalle() as $campo => $textos) {
            foreach ((array) $textos as $texto) {
                if (is_string($texto) && $texto !== '') {
                    $mensajes[] = $texto;
                }
            }
        }

        return $mensajes !== [] ? implode(' · ', array_unique($mensajes)) : $e->mensaje();
    }

    /** `CONFIRMACION_RECHAZADA`, `CONFIGURACION_INCOMPLETA`, `ESTADO_INVALIDO`…: el Central explica en `mensaje`. */
    private function mensajeDelCentral(RukozException $e): string
    {
        $mensaje = trim($e->mensaje());

        return $mensaje !== '' ? rtrim($mensaje, '.').'.' : 'No se pudo crear la venta.';
    }

    private function error(string $mensaje): ValidationException
    {
        return ValidationException::withMessages(['confirmacion' => $mensaje]);
    }

    private static function numero(float $valor): int|float
    {
        return floor($valor) == $valor ? (int) $valor : round($valor, 2);
    }

    private static function plata(float $valor): string
    {
        return '$'.number_format($valor, 2, ',', '.');
    }
}
