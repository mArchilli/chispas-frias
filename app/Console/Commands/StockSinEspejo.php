<?php

namespace App\Console\Commands;

use App\Models\Combo;
use App\Rukoz\EspejoStock;
use Illuminate\Console\Command;

class StockSinEspejo extends Command
{
    protected $signature = 'chispasfrias:stock-sin-espejo';

    protected $description = 'Lista productos y variantes activos sin fila en rukoz_stock o con un stock local distinto del espejo (sale con código 1 si hay alguno)';

    public function handle(): int
    {
        if (! EspejoStock::activo()) {
            $this->components->warn('El espejo de stock está apagado (INTEGRACION_STOCK / RUKOZ_INTEGRACION): el chequeo igual compara contra lo que haya en rukoz_stock.');
        }

        $auditoria = EspejoStock::auditar();

        $this->components->twoColumnDetail('Productos y variantes activos revisados', (string) $auditoria['revisados']);
        $this->components->twoColumnDetail('Sin vincular en el Central (informativo, no se tocan)', (string) $auditoria['sin_vincular']);
        $this->components->twoColumnDetail('Combos activos (sin columna: se calculan con sus componentes)', (string) Combo::query()->where('is_active', true)->count());

        if ($auditoria['problemas'] === []) {
            $this->components->info('Todo coincide con el espejo.');

            return self::SUCCESS;
        }

        $this->table(
            ['Tipo', 'ID', 'Ítem', 'Local', 'Espejo', 'Motivo'],
            array_map(fn (array $p) => [$p[0], $p[1], $p[2], $this->mostrar($p[3]), $p[4] === null && $p[5] === 'sin fila en rukoz_stock' ? '—' : $this->mostrar($p[4]), $p[5]], $auditoria['problemas'])
        );
        $this->components->error(count($auditoria['problemas']).' ítem(s) sin espejo. Corré `php artisan chispasfrias:stock-cargar` para ponerlos al día.');

        return self::FAILURE;
    }

    private function mostrar(?int $stock): string
    {
        return $stock === null ? 'NULL (ilimitado)' : (string) $stock;
    }
}
