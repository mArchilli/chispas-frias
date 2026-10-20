<?php

namespace App\Console\Commands;

use App\Enums\EstadoOrden;
use App\Models\Order;
use App\Rukoz\CheckoutCentral;
use App\Rukoz\ReservaDePedido;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * Cancela los pedidos con reserva en el Central que llevan más de N días sin confirmarse (PEDIDOS_CANCELAR_DIAS, 3 por
 * defecto). Las reservas del Central NO vencen: si la web no las cancela, el stock queda apartado para siempre
 * (el Central solo avisa a sus administradores, no libera nada solo).
 *
 * Solo toca pedidos con `rukoz_ref`, todavía pendientes (no despachados) y con la reserva viva: los de la web sola (sin
 * `rukoz_ref`) no se cancelan nunca solos. Cancela igual que el admin (`ReservaDePedido::cancelar`): primero el Central,
 * después el pedido local liberando el cupón; si el Central no responde, la cancelación queda en las compensaciones del
 * paquete.
 */
class CancelarReservasViejas extends Command
{
    protected $signature = 'chispasfrias:cancelar-reservas-viejas
        {--dry-run : Solo lista lo que cancelaría; no cancela nada ni llama al Central}
        {--dias= : Días sin confirmar a partir de los cuales se cancela (por defecto PEDIDOS_CANCELAR_DIAS)}';

    protected $description = 'Cancela los pedidos con reserva en el Central sin confirmar hace más de N días (PEDIDOS_CANCELAR_DIAS)';

    public function handle(ReservaDePedido $reservas): int
    {
        $dias = $this->option('dias') !== null ? max(1, (int) $this->option('dias')) : CheckoutCentral::diasParaCancelar();
        $simulacro = (bool) $this->option('dry-run');

        if (! $simulacro && ! config('rukoz.habilitada')) {
            $this->components->warn('RUKOZ_INTEGRACION está apagado: no se puede cancelar nada en el Central. Usá --dry-run para ver qué se cancelaría.');

            return self::SUCCESS;
        }

        // «Más de N días»: exactamente N días cumplidos todavía no; un segundo después, sí.
        $limite = now()->subDays($dias);

        $pedidos = Order::query()
            ->whereNotNull('rukoz_ref')
            ->where('estado', EstadoOrden::Pendiente)
            ->where('rukoz_estado', Order::RESERVA_RESERVADA)
            ->where('created_at', '<', $limite)
            ->orderBy('created_at')
            ->get();

        if ($pedidos->isEmpty()) {
            $this->components->info("No hay pedidos con reserva y más de {$dias} día(s) sin confirmar.");

            return self::SUCCESS;
        }

        $motivo = "Cancelado automáticamente ({$dias} días sin confirmar)";

        $this->table(
            ['Pedido', 'Referencia', 'Creado', 'Antigüedad'],
            $pedidos->map(fn (Order $p) => [$p->id, $p->rukoz_ref, $p->created_at->toDateTimeString(), $p->created_at->diffForHumans(null, true)])->all()
        );

        if ($simulacro) {
            $this->components->info($pedidos->count().' pedido(s) se cancelarían (dry-run: no se tocó nada). Motivo: '.$motivo);

            return self::SUCCESS;
        }

        $canceladas = 0;
        $fallidas = 0;

        foreach ($pedidos as $pedido) {
            try {
                $reservas->cancelar($pedido, $motivo);
                $canceladas++;
            } catch (ValidationException $e) {
                $fallidas++;
                $this->components->error("Pedido #{$pedido->id}: ".collect($e->errors())->flatten()->first());
            }
        }

        $this->components->info("Canceladas: {$canceladas}".($fallidas > 0 ? " — con error: {$fallidas}" : '').'.');

        return $fallidas > 0 ? self::FAILURE : self::SUCCESS;
    }
}
