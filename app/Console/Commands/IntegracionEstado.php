<?php

namespace App\Console\Commands;

use App\Enums\EstadoOrden;
use App\Models\Order;
use App\Rukoz\CheckoutCentral;
use App\Rukoz\EspejoStock;
use Illuminate\Console\Command;
use Rukoz\CoreClient\Compensaciones\Compensacion;
use Rukoz\CoreClient\Errores\RukozException;
use Rukoz\CoreClient\Facades\Rukoz;
use Throwable;

class IntegracionEstado extends Command
{
    protected $signature = 'chispasfrias:integracion-estado';

    protected $description = 'Estado de la integración con el Central: interruptores, ping, ítems sin espejo y reservas pendientes de compensar (código 1 si algo está mal)';

    public function handle(): int
    {
        $mal = false;
        $integracion = (bool) config('rukoz.habilitada');

        $this->components->twoColumnDetail('<fg=white;options=bold>Interruptores</>');
        $this->components->twoColumnDetail('RUKOZ_INTEGRACION (paquete)', $this->onOff($integracion));
        $this->components->twoColumnDetail('INTEGRACION_CATALOGO (10.1)', $this->onOff((bool) config('integracion.catalogo')));
        $this->components->twoColumnDetail('INTEGRACION_STOCK (10.2)', $this->onOff((bool) config('integracion.stock')));
        $this->components->twoColumnDetail(
            'INTEGRACION_CHECKOUT (10.3)',
            $this->onOff(CheckoutCentral::solicitado()).(CheckoutCentral::solicitado() ? (CheckoutCentral::activo() ? ' → <fg=green>el checkout USA el Central</>' : ' → <fg=red>el checkout NO usa el Central</>') : '')
        );
        $this->components->twoColumnDetail('INTEGRACION_CONFIRMACION (10.4)', $this->onOff((bool) config('integracion.confirmacion')));
        $this->components->twoColumnDetail('Vender sin reserva si el Central no responde', CheckoutCentral::permiteVenderSinReserva() ? '<fg=yellow>SÍ</>' : 'no (el pedido no se crea)');
        $this->components->twoColumnDetail('Cancelación automática de reservas', CheckoutCentral::diasParaCancelar().' días sin confirmar');

        if (($advertencia = CheckoutCentral::advertencia()) !== null) {
            $this->components->warn($advertencia);
            $mal = true;
        }

        $this->newLine();
        $this->components->twoColumnDetail('<fg=white;options=bold>Central</>');
        $mal = $this->ping($integracion) || $mal;

        $this->newLine();
        $this->components->twoColumnDetail('<fg=white;options=bold>Stock</>');
        $auditoria = EspejoStock::auditar();
        $sinEspejo = count($auditoria['problemas']);
        $this->components->twoColumnDetail('Ítems activos sin espejo', $sinEspejo === 0 ? '<fg=green>0</>' : "<fg=red>{$sinEspejo}</> (corré chispasfrias:stock-sin-espejo y chispasfrias:stock-cargar)");
        $this->components->twoColumnDetail('Revisados / sin vincular en el Central', $auditoria['revisados'].' / '.$auditoria['sin_vincular']);
        // Sin espejo es un problema cuando el checkout depende de él (con el checkout apagado es solo informativo).
        $mal = $mal || ($sinEspejo > 0 && CheckoutCentral::solicitado());

        $this->newLine();
        $this->components->twoColumnDetail('<fg=white;options=bold>Reservas</>');
        $mal = $this->reservas() || $mal;

        return $mal ? self::FAILURE : self::SUCCESS;
    }

    /** @return bool true si hay un problema */
    private function ping(bool $integracion): bool
    {
        if (! $integracion) {
            $this->components->twoColumnDetail('Ping al Central', 'omitido (RUKOZ_INTEGRACION apagado)');

            return false;
        }

        $inicio = microtime(true);

        try {
            $respuesta = Rukoz::ping();
            $ms = (int) round((microtime(true) - $inicio) * 1000);
            $this->components->twoColumnDetail('Ping al Central', "<fg=green>OK</> ({$ms} ms) — ".($respuesta['integracion']['nombre'] ?? 'integración'));
        } catch (RukozException $e) {
            $this->components->twoColumnDetail('Ping al Central', "<fg=red>FALLA</> — {$e->codigo()}");

            return true;
        } catch (Throwable $e) {
            $this->components->twoColumnDetail('Ping al Central', '<fg=red>FALLA</> — '.$e::class);

            return true;
        }

        $this->components->twoColumnDetail('Circuito', Rukoz::circuitoAbierto() ? '<fg=red>ABIERTO</>' : 'cerrado');

        return Rukoz::circuitoAbierto();
    }

    /** @return bool true si hay un problema */
    private function reservas(): bool
    {
        $dias = CheckoutCentral::diasParaCancelar();
        $abiertas = Order::query()->whereNotNull('rukoz_ref')->where('estado', EstadoOrden::Pendiente)->where('rukoz_estado', Order::RESERVA_RESERVADA);
        $viejas = (clone $abiertas)->where('created_at', '<', now()->subDays($dias))->count();

        $this->components->twoColumnDetail('Pedidos con reserva abierta', (string) (clone $abiertas)->count());
        $this->components->twoColumnDetail("…con más de {$dias} días (los cancela el cron)", $viejas === 0 ? '0' : "<fg=yellow>{$viejas}</>");

        $pendientes = Compensacion::query()->pendientes()->get(['id', 'ref', 'tipo']);
        $fallidas = Compensacion::query()->where('estado', Compensacion::FALLIDA)->count();
        $conPedido = Order::query()->whereIn('rukoz_ref', $pendientes->pluck('ref'))->count();
        $canceladosPendientes = Order::query()->where('rukoz_estado', Order::RESERVA_CANCELACION_PENDIENTE)->count();

        $porTipo = $pendientes->countBy('tipo')->map(fn (int $n, string $tipo) => "{$tipo}: {$n}")->implode(', ');

        $this->components->twoColumnDetail(
            'Reservas pendientes de compensar',
            $pendientes->isEmpty() ? '<fg=green>0</>' : '<fg=yellow>'.$pendientes->count().'</> ('.$porTipo.')'
        );
        $this->components->twoColumnDetail('…de pedidos que existen en la web', (string) $conPedido);
        $this->components->twoColumnDetail('Pedidos cancelados esperando que el Central confirme', (string) $canceladosPendientes);
        $this->components->twoColumnDetail('Compensaciones fallidas (vencieron: requieren una persona)', $fallidas === 0 ? '<fg=green>0</>' : "<fg=red>{$fallidas}</>");

        // Una reserva vieja es stock apartado que el cron horario ya tendría que haber cancelado (¿está corriendo
        // `schedule:run`? ¿el Central responde?); una compensación fallida venció sin resolverse: las dos piden una persona.
        return $fallidas > 0 || $viejas > 0;
    }

    private function onOff(bool $valor): string
    {
        return $valor ? '<fg=green>encendido</>' : 'apagado';
    }
}
