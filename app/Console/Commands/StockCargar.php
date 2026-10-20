<?php

namespace App\Console\Commands;

use App\Rukoz\EspejoStock;
use Illuminate\Console\Command;
use Rukoz\CoreClient\Errores\RukozException;
use Rukoz\CoreClient\Facades\Rukoz;

class StockCargar extends Command
{
    protected $signature = 'chispasfrias:stock-cargar {--local : No llama al Central: solo vuelca lo que ya hay en rukoz_stock a las columnas de stock}';

    protected $description = 'Carga completa del stock del Central (GET /stock → rukoz_stock) y lo vuelca a productos y variantes. Idempotente';

    public function handle(EspejoStock $espejo): int
    {
        if (! EspejoStock::activo()) {
            $this->components->error('El espejo de stock está apagado: hace falta INTEGRACION_STOCK=true y RUKOZ_INTEGRACION=true.');

            return self::FAILURE;
        }

        if (! $this->option('local')) {
            try {
                // `rukoz:sync-stock` del paquete: lee GET /stock completo y lo guarda en rukoz_stock por versión. Su evento
                // solo trae lo que cambió en el caché, así que el volcado a las columnas se hace aparte, sobre TODO el caché.
                $lectura = EspejoStock::sinEscuchar(fn () => Rukoz::sincronizarStock());
            } catch (RukozException $e) {
                $this->components->error('El Central no devolvió el stock: '.$e->codigo().' — '.$e->getMessage().'. No se tocó nada.');

                return self::FAILURE;
            }

            $this->components->twoColumnDetail('Leídos del Central', $lectura['recibidos'].' ítems en '.$lectura['paginas'].' página(s), versión '.$lectura['version']);
            $this->components->twoColumnDetail('Cambiaron en rukoz_stock', (string) $lectura['cambiados']);
        }

        $resumen = $espejo->desdeElCache();

        $this->newLine();
        $this->components->twoColumnDetail('Actualizados (el stock local cambió)', (string) $resumen['actualizados']);
        $this->components->twoColumnDetail('Sin cambios (ya coincidían)', (string) $resumen['sin_cambios']);
        $this->components->twoColumnDetail('Ilimitados (a pedido → 9999 en producto, NULL en variante), incluidos arriba', (string) $resumen['ilimitados']);
        $this->components->twoColumnDetail('Sin vincular en el Central (no se tocan)', (string) $resumen['sin_vincular']);

        if ($resumen['inexistentes'] > 0) {
            $this->components->warn($resumen['inexistentes'].' ítem(s) del Central no existen en esta web (ids viejos): se ignoraron.');
        }

        return self::SUCCESS;
    }
}
