<?php

namespace App\Rukoz;

/**
 * Un pedido listo para el Central: el body (`payload`) y, por cada línea de `payload['items']` y en el mismo orden, qué
 * es para el cliente (título, color, producto o combo), para armar mensajes legibles cuando el Central rechaza una línea
 * (`SinStockException::items()` la señala por `indice`).
 */
final readonly class PedidoArmado
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  list<array{titulo: string, variante: ?string, product_id: ?int, combo_id: ?int, cantidad: int, stock: list<array{0: string, 1: string}>}>  $lineas
     */
    public function __construct(
        public array $payload,
        public array $lineas,
    ) {}
}
