<?php

namespace App\Rukoz;

use App\Support\Stock;
use Illuminate\Support\Facades\Log;
use Rukoz\CoreClient\Errores\CentralNoDisponibleException;
use Rukoz\CoreClient\Errores\IntegracionApagadaException;
use Rukoz\CoreClient\Errores\RukozException;
use Rukoz\CoreClient\Errores\SinStockException;
use Throwable;

/**
 * Traduce lo que contesta el Central (excepciones del paquete) a lo que ve quien compra o administra. Se decide siempre por
 * el tipo de excepción / `codigo()`, nunca por el texto. Ningún mensaje lleva códigos técnicos ni datos del cliente.
 */
final class ErroresDelCentral
{
    public const MENSAJE_CENTRAL_CAIDO = 'No pudimos confirmar el stock en este momento. Probá de nuevo en unos minutos o escribinos por WhatsApp.';

    /** Para un ítem que el Central no puede vender (sin vincular, selección inválida): es un problema de catálogo, no del cliente. */
    public const MENSAJE_ITEM_NO_DISPONIBLE = 'Uno de los productos no está disponible por el momento. Escribinos por WhatsApp y lo resolvemos.';

    /**
     * Una entrada por cada línea que no alcanza, nombrando el producto y el color, con las mismas palabras que el chequeo
     * contra el espejo («No queda stock de X (Color).» / «Solo quedan N unidades de X (Color) (pediste M).») y la misma
     * forma que `stock_insuficiente` del checkout.
     *
     * @return list<array{product_id: int|null, product_title: string|null, product_variant_id: int|null, variant_name: string|null, cantidad_solicitada: int, stock_disponible: int, mensaje: string}>
     */
    public static function sinStock(SinStockException $e, PedidoArmado $armado): array
    {
        $faltas = [];

        foreach ($e->items() as $falta) {
            $linea = $armado->lineas[(int) ($falta['indice'] ?? -1)] ?? null;

            if ($linea === null) {
                $faltas[] = [
                    'product_id' => null,
                    'product_title' => null,
                    'product_variant_id' => null,
                    'variant_name' => null,
                    'cantidad_solicitada' => (int) ($falta['pedido'] ?? 0),
                    'stock_disponible' => max(0, (int) ($falta['disponible'] ?? 0)),
                    'mensaje' => 'No queda stock suficiente de alguno de los productos del pedido.',
                ];

                continue;
            }

            $disponible = max(0, (int) ($falta['disponible'] ?? 0));
            $pedido = (int) ($falta['pedido'] ?? $linea['cantidad']);
            $esCombo = $linea['combo_id'] !== null;
            $titulo = $esCombo ? 'combo "'.$linea['titulo'].'"' : $linea['titulo'];

            $faltas[] = [
                'product_id' => $linea['product_id'],
                'product_title' => $linea['titulo'],
                'product_variant_id' => $linea['variante'] !== null && ($falta['tipo'] ?? null) === 'variante' ? (int) $falta['id'] : null,
                'variant_name' => $linea['variante'],
                'cantidad_solicitada' => $pedido,
                'stock_disponible' => $disponible,
                'mensaje' => Stock::mensajeFaltante($titulo, $linea['variante'], $disponible, pedido: $pedido),
            ];
        }

        return $faltas === []
            ? [[
                'product_id' => null, 'product_title' => null, 'product_variant_id' => null, 'variant_name' => null,
                'cantidad_solicitada' => 0, 'stock_disponible' => 0,
                'mensaje' => 'No queda stock suficiente de alguno de los productos del pedido.',
            ]]
            : $faltas;
    }

    /**
     * Ítems del Central (`[tipo, id]`) cuyo stock hay que releer porque la reserva no alcanzó: el de cada línea señalada.
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function paresParaRefrescar(SinStockException $e, PedidoArmado $armado): array
    {
        $pares = [];

        foreach ($e->items() as $falta) {
            foreach ($armado->lineas[(int) ($falta['indice'] ?? -1)]['stock'] ?? [] as $par) {
                $pares[$par[0].':'.$par[1]] = $par;
            }

            if (isset($falta['tipo'], $falta['id'])) {
                $pares[$falta['tipo'].':'.$falta['id']] = [(string) $falta['tipo'], (string) $falta['id']];
            }
        }

        return array_values($pares);
    }

    /** Mensaje para el admin cuando el Central no deja cancelar. */
    public static function paraElAdmin(Throwable $e, string $accion): string
    {
        return match (true) {
            $e instanceof CentralNoDisponibleException => "No pudimos comunicarnos con el Sistema Central, así que no se pudo {$accion}. No se guardó ningún cambio: probá de nuevo en unos minutos.",
            $e instanceof IntegracionApagadaException => "La integración con el Sistema Central está apagada y este pedido tiene stock reservado allá: no se puede {$accion} hasta volver a encenderla.",
            default => "El Sistema Central rechazó la operación y no se pudo {$accion}. Avisá a soporte.",
        };
    }

    /** Para lo que no es culpa de quien compra ni del admin: queda en el log del servidor, sin datos del cliente. */
    public static function registrar(RukozException $e, string $contexto): void
    {
        Log::warning("Sistema Central: {$contexto} — {$e->codigo()} (HTTP {$e->http()})", ['request_id' => $e->requestId()]);
    }
}
