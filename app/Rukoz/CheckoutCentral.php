<?php

namespace App\Rukoz;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * El checkout contra el Central (10.3): cuándo se usa y el «intento» de un pedido.
 *
 * REGLA DE ENCENDIDO. `INTEGRACION_CHECKOUT` exige `INTEGRACION_STOCK` y `RUKOZ_INTEGRACION`: sin el espejo de stock el
 * checkout reservaría contra un stock que la web no ve. Con la combinación inválida el checkout NO usa el Central (sigue
 * como siempre, con stock local), `advertir()` deja un warning en el log y `chispasfrias:integracion-estado` lo informa.
 *
 * INTENTO. La referencia externa del pedido (`rukoz_ref`) es la clave de idempotencia: se genera ANTES de reservar y
 * se reutiliza mientras el pedido sea el mismo (mismo contenido), así un doble clic, un reintento tras un timeout o un
 * reenvío del navegador no crean dos reservas. Se guarda en el caché (no en la sesión: dos requests simultáneos de un
 * doble clic leen la sesión antes de que ninguno la escriba) junto con la hora del intento (`creado_at` forma parte de lo
 * que el Central compara: tiene que ser byte a byte el mismo en cada reintento).
 */
final class CheckoutCentral
{
    private const PREFIJO_REF = 'CHF-';

    /** ¿El checkout usa el Central? Solo con los tres interruptores en orden. */
    public static function activo(): bool
    {
        return self::solicitado() && self::combinacionValida();
    }

    /** ¿Pidieron encenderlo (INTEGRACION_CHECKOUT)? Puede estar pedido pero no activo (combinación inválida). */
    public static function solicitado(): bool
    {
        return (bool) config('integracion.checkout');
    }

    /** ¿Están encendidos RUKOZ_INTEGRACION e INTEGRACION_STOCK, que el checkout necesita? */
    public static function combinacionValida(): bool
    {
        return (bool) config('rukoz.habilitada') && (bool) config('integracion.stock');
    }

    /** Qué falta si el checkout está pedido pero no puede usarse; `null` si está bien (o no está pedido). */
    public static function advertencia(): ?string
    {
        if (! self::solicitado() || self::combinacionValida()) {
            return null;
        }

        $faltan = array_filter([
            config('rukoz.habilitada') ? null : 'RUKOZ_INTEGRACION',
            config('integracion.stock') ? null : 'INTEGRACION_STOCK',
        ]);

        return 'INTEGRACION_CHECKOUT está encendido pero falta '.implode(' y ', $faltan).': el checkout NO usa el Central (sigue con stock local).';
    }

    /**
     * Deja el warning en el log (a lo sumo una vez por hora: se evalúa en cada arranque de la app). No falla nunca.
     */
    public static function advertir(): void
    {
        $mensaje = self::advertencia();

        if ($mensaje === null) {
            return;
        }

        try {
            if (Cache::add('integracion:advertencia-checkout', true, 3600)) {
                Log::warning($mensaje);
            }
        } catch (Throwable) {
            // Un caché sin migrar (instalación nueva) no puede romper el arranque.
        }
    }

    /**
     * DECISIÓN DE NEGOCIO, fácil de cambiar: ¿se puede vender cuando el Central no responde (caído, timeout o circuito
     * abierto)?
     *
     *  - false (por defecto): no. El pedido no se crea; el cliente ve un mensaje amable con el botón de WhatsApp y su
     *    carrito armado en el texto. Nunca se vende un stock que no se pudo reservar.
     *  - true (INTEGRACION_CHECKOUT_SIN_RESERVA): el pedido se crea igual, SIN `rukoz_ref`, descontando stock local como
     *    antes. El Central no se entera: esa venta hay que cargarla a mano allá, y el próximo evento de stock pisa el
     *    descuento local.
     */
    public static function permiteVenderSinReserva(): bool
    {
        return (bool) config('integracion.checkout_sin_reserva', false);
    }

    /** Días sin confirmar a partir de los cuales un pedido con reserva se cancela solo. */
    public static function diasParaCancelar(): int
    {
        return max(1, (int) config('integracion.cancelar_dias', 3));
    }

    /**
     * Huella del contenido de un pedido (sin `external_ref` ni `creado_at`): dos requests con el mismo carrito, los mismos
     * datos del cliente y los mismos precios dan la misma huella.
     *
     * @param  array<string, mixed>  $payload  El body de `POST /pedidos` armado SIN ref ni creado_at.
     */
    public static function huella(array $payload): string
    {
        return sha1(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION) ?: serialize($payload));
    }

    /**
     * El intento vigente para esa huella (lo crea si no hay): `{ref, creado_at, pedido_id}`. `pedido_id` queda en null hasta
     * que el pedido se crea.
     *
     * @return array{ref: string, creado_at: string, pedido_id: int|null}
     */
    public static function intento(string $huella): array
    {
        $clave = self::claveIntento($huella);
        $intento = Cache::get($clave);

        if (is_array($intento) && isset($intento['ref'], $intento['creado_at'])) {
            return $intento + ['pedido_id' => null];
        }

        $intento = [
            'ref' => self::PREFIJO_REF.Str::upper((string) Str::ulid()),
            'creado_at' => now()->toIso8601String(),
            'pedido_id' => null,
        ];

        Cache::put($clave, $intento, 3600);

        return $intento;
    }

    /** El pedido ya existe: el intento se conserva unos minutos para frenar un doble envío idéntico. */
    public static function intentoCompletado(string $huella, array $intento, int $pedidoId): void
    {
        Cache::put(
            self::claveIntento($huella),
            ['pedido_id' => $pedidoId] + $intento,
            max(1, (int) config('integracion.ventana_doble_envio', 120))
        );
    }

    /** El Central rechazó el pedido (sin stock, sin vincular…): un reenvío igual tiene que armar un intento nuevo. */
    public static function olvidarIntento(string $huella): void
    {
        Cache::forget(self::claveIntento($huella));
    }

    public static function claveLock(string $huella): string
    {
        return 'checkout-central-lock:'.$huella;
    }

    private static function claveIntento(string $huella): string
    {
        return 'checkout-central:'.$huella;
    }
}
