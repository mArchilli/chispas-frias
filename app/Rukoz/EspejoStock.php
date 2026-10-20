<?php

namespace App\Rukoz;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Stock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Rukoz\CoreClient\Eventos\StockActualizado;
use Rukoz\CoreClient\Facades\Rukoz;
use Rukoz\CoreClient\Stock\CacheDeStock;
use Rukoz\CoreClient\Stock\StockItem;
use Throwable;

/**
 * Espejo del stock del Central en las columnas de Chispas Frías (`products.stock` y `product_variants.stock`).
 *
 * La tienda sigue leyendo esas dos columnas; con INTEGRACION_STOCK encendido las llena el Central en vez del admin.
 * De dónde sale cada valor (siempre el «Disponible web» del Central):
 *
 *  - un número            → ese número, nunca negativo (mínimo 0);
 *  - `a_pedido` o null    → «ilimitado» según la convención de la web (App\Support\Stock): 9999 en un producto,
 *                           NULL en una variante;
 *  - `vinculado = false`  → NO se toca: el ítem sigue con su stock local (`no_aplica` y sin vincular llegan así).
 *
 * Los combos no tienen columna: su disponibilidad se deriva de los componentes (Combo::estaDisponible y las
 * validaciones del carrito), así que el `combo` que informa el Central se ignora. Un producto con variantes tampoco
 * tiene número propio (queda `no_aplica`): lo informan sus variantes.
 *
 * ORDEN E IDEMPOTENCIA. El paquete guarda cada ítem en `rukoz_stock` con la `version` del stock publicado (un contador
 * de la integración que solo crece) y descarta lo que llega con una versión menor a la guardada: el `StockActualizado`
 * solo trae ítems que ganaron esa regla, de modo que un evento viejo que llega después de uno nuevo nunca llega acá. Como
 * red extra, antes de escribir se compara la versión del evento con la de `rukoz_stock`: si el caché ya tiene una más
 * nueva se salta ese ítem. Escribir dos veces lo mismo no cambia nada, y solo se hace UPDATE cuando el valor difiere
 * (sin tocar `updated_at`, que ordena la home).
 */
final class EspejoStock
{
    /** Tipos del Central que tienen columna en esta web → tabla. */
    private const TABLAS = ['producto' => 'products', 'variante' => 'product_variants'];

    private static bool $pausado = false;

    /** Solo con la integración general encendida y el interruptor de stock; si no, la web funciona como siempre. */
    public static function activo(): bool
    {
        return (bool) config('integracion.stock') && (bool) config('rukoz.habilitada');
    }

    /**
     * Listener de `StockActualizado` (se registra en AppServiceProvider solo con INTEGRACION_STOCK). Corre dentro de la
     * transacción del paquete: sin HTTP y rápido.
     */
    public function alActualizarse(StockActualizado $evento): void
    {
        if (self::$pausado || ! self::activo()) {
            return;
        }

        $filas = StockItem::query()
            ->whereIn('tipo', array_keys(self::TABLAS))
            ->whereIn('external_id', collect($evento->items)->pluck('id')->map(fn ($id) => (string) $id)->all())
            ->get()
            ->keyBy(fn (StockItem $fila) => $fila->tipo.':'.$fila->external_id);

        $items = [];

        foreach ($evento->items as $item) {
            $clave = ($item['tipo'] ?? '').':'.($item['id'] ?? '');
            $versionItem = (int) ($item['version'] ?? $evento->version);

            if (($fila = $filas->get($clave)) !== null && $fila->version > $versionItem) {
                continue;
            }

            $items[] = $item;
        }

        $this->aplicar($items);
    }

    /**
     * Vuelca a las columnas TODO lo que hay en `rukoz_stock` (no solo lo que cambió). Es lo que usa la carga completa.
     *
     * @return array{actualizados: int, sin_cambios: int, ilimitados: int, sin_vincular: int, inexistentes: int}
     */
    public function desdeElCache(): array
    {
        $total = self::resumenVacio();

        StockItem::query()
            ->whereIn('tipo', array_keys(self::TABLAS))
            ->orderBy('id')
            ->chunkById(500, function ($filas) use (&$total) {
                $parcial = $this->aplicar($filas->map(fn (StockItem $fila) => $fila->paraLaWeb())->all());

                foreach ($parcial as $campo => $cantidad) {
                    $total[$campo] += $cantidad;
                }
            });

        return $total;
    }

    /**
     * Ejecuta algo sin que el listener escriba (la carga completa lo hace ella misma, para poder contar).
     *
     * @template T
     *
     * @param  callable(): T  $accion
     * @return T
     */
    public static function sinEscuchar(callable $accion): mixed
    {
        self::$pausado = true;

        try {
            return $accion();
        } finally {
            self::$pausado = false;
        }
    }

    /**
     * El valor que tiene que tener la columna según un ítem de stock del Central, o `null` si no hay que tocarla
     * (sin vincular, o un tipo sin columna). El valor es `['stock' => ?int]` para distinguir «no tocar» de «NULL».
     *
     * @param  array<string, mixed>  $item  Con la forma de `GET /stock`.
     * @return array{stock: int|null}|null
     */
    public static function valorDe(array $item): ?array
    {
        $tipo = $item['tipo'] ?? '';

        if (! isset(self::TABLAS[$tipo])) {
            return null;
        }

        if (! ($item['vinculado'] ?? false)) {
            return null;
        }

        if (($item['a_pedido'] ?? false) || ! isset($item['disponible']) || ! is_numeric($item['disponible'])) {
            return ['stock' => Stock::ilimitadoDe($tipo)];
        }

        return ['stock' => max(0, (int) $item['disponible'])];
    }

    /**
     * Ids de esta web cuyo stock manda el Central (hay fila vinculada en `rukoz_stock`). Solo ellos pasan a ser de solo
     * lectura en el admin; el resto —productos con variantes, ítems sin vincular o que el Central todavía no vio— sigue
     * editable con su stock local. Con la integración apagada no hay ninguno.
     *
     * @param  'producto'|'variante'  $tipo
     * @param  list<int|string>  $ids
     * @return list<int>
     */
    public static function gestionados(string $tipo, array $ids): array
    {
        if (! self::activo() || $ids === []) {
            return [];
        }

        return StockItem::query()
            ->where('tipo', $tipo)
            ->where('vinculado', true)
            ->whereIn('external_id', array_map('strval', $ids))
            ->pluck('external_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public static function gestionado(string $tipo, int $id): bool
    {
        return in_array($id, self::gestionados($tipo, [$id]), true);
    }

    /**
     * Relee del Central el stock de unos ítems puntuales (`GET /stock?tipo=&ids[]=`), lo guarda en `rukoz_stock` por versión
     * y lo vuelca a las columnas (el listener de `StockActualizado` hace el volcado). Se usa cuando el Central rechaza una
     * reserva por falta de stock: el espejo estaba desactualizado, y así la tienda deja de ofrecer lo que no hay sin esperar
     * al próximo evento. Nunca lanza: es un intento de mejora, no puede romper el checkout.
     *
     * @param  list<array{0: string, 1: string}>  $items  `[tipo, id]` con tipo `producto`, `variante` o `combo`.
     */
    public static function refrescar(array $items): void
    {
        if (! self::activo() || $items === []) {
            return;
        }

        $porTipo = [];

        foreach ($items as [$tipo, $id]) {
            $porTipo[$tipo][(string) $id] = (string) $id;
        }

        foreach ($porTipo as $tipo => $ids) {
            try {
                $pagina = Rukoz::stock(['tipo' => $tipo, 'ids' => array_values($ids)]);
                $version = (int) ($pagina['version'] ?? 0);
                $filas = (array) ($pagina['items'] ?? []);
                $aplicado = app(CacheDeStock::class)->aplicar($filas, $version);

                if ($aplicado['cambiados'] !== []) {
                    event(new StockActualizado($aplicado['cambiados'], $version, StockActualizado::ORIGEN_SYNC));
                }

                // También se vuelca lo que el caché ya tenía bien: si la que estaba atrasada era la columna (no el caché), el
                // evento de arriba no la alcanza. Es lo que acaba de decir el Central y escribir lo mismo dos veces no cambia nada.
                if (self::activo()) {
                    (new self)->aplicar($filas);
                }
            } catch (Throwable $e) {
                Log::warning('No se pudo refrescar el espejo de stock tras un rechazo del Central: '.$e::class);
            }
        }
    }

    /**
     * Qué productos y variantes ACTIVOS no tienen espejo: sin fila en `rukoz_stock`, o con un stock local distinto del
     * que informó el Central. Lo usa `chispasfrias:stock-sin-espejo`.
     *
     * @return array{revisados: int, sin_vincular: int, problemas: list<array{0: string, 1: int, 2: string, 3: int|null, 4: int|null, 5: string}>}
     *                                                                                                       cada problema: [tipo, id, nombre, local, espejo, motivo]
     */
    public static function auditar(): array
    {
        $filas = StockItem::query()
            ->whereIn('tipo', array_keys(self::TABLAS))
            ->get()
            ->keyBy(fn (StockItem $fila) => $fila->tipo.':'.$fila->external_id);

        $problemas = [];
        $sinVincular = 0;
        $revisados = 0;

        // Un producto con variantes no tiene número propio (lo informan sus variantes): se revisan sus variantes activas.
        $items = Product::query()->where('is_active', true)->whereDoesntHave('variants')->orderBy('id')->get()
            ->map(fn (Product $p) => ['producto', $p->id, $p->title, $p->stock])
            ->concat(
                ProductVariant::query()->with('product')->where('is_active', true)
                    ->whereHas('product', fn ($q) => $q->where('is_active', true))
                    ->orderBy('product_id')->orderBy('id')->get()
                    ->map(fn (ProductVariant $v) => ['variante', $v->id, $v->product->title.' — '.$v->name, $v->stock])
            );

        foreach ($items as [$tipo, $id, $nombre, $local]) {
            $revisados++;
            $fila = $filas->get($tipo.':'.$id);
            $local = $local === null ? null : (int) $local;

            if ($fila === null) {
                $problemas[] = [$tipo, $id, $nombre, $local, null, 'sin fila en rukoz_stock'];

                continue;
            }

            $esperado = self::valorDe($fila->paraLaWeb());

            if ($esperado === null) {
                $sinVincular++; // el Central no lo tiene vinculado: sigue con su stock local, no es un error de espejo

                continue;
            }

            if ($esperado['stock'] !== $local) {
                $problemas[] = [$tipo, $id, $nombre, $local, $esperado['stock'], 'el stock local difiere del espejo'];
            }
        }

        return ['revisados' => $revisados, 'sin_vincular' => $sinVincular, 'problemas' => $problemas];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{actualizados: int, sin_cambios: int, ilimitados: int, sin_vincular: int, inexistentes: int}
     */
    private function aplicar(array $items): array
    {
        $resumen = self::resumenVacio();
        $porTipo = [];

        foreach ($items as $item) {
            $tipo = $item['tipo'] ?? '';

            if (! isset(self::TABLAS[$tipo])) {
                continue; // combos y otros: sin columna
            }

            $valor = self::valorDe($item);

            if ($valor === null) {
                $resumen['sin_vincular']++;

                continue;
            }

            $porTipo[$tipo][(int) $item['id']] = $valor['stock']; // el último repetido gana
        }

        foreach ($porTipo as $tipo => $valores) {
            $tabla = self::TABLAS[$tipo];
            $actuales = DB::table($tabla)->whereIn('id', array_keys($valores))->pluck('stock', 'id');

            foreach ($valores as $id => $nuevo) {
                if (! $actuales->has($id)) {
                    $resumen['inexistentes']++;

                    continue;
                }

                if ($nuevo === Stock::ilimitadoDe($tipo)) {
                    $resumen['ilimitados']++;
                }

                $actual = $actuales[$id] === null ? null : (int) $actuales[$id];

                if ($actual === $nuevo) {
                    $resumen['sin_cambios']++;

                    continue;
                }

                // DB::table y no el modelo: el espejo no es una edición, no tiene que mover `updated_at` (ordena la home).
                DB::table($tabla)->where('id', $id)->update(['stock' => $nuevo]);
                $resumen['actualizados']++;
            }
        }

        return $resumen;
    }

    /** @return array{actualizados: int, sin_cambios: int, ilimitados: int, sin_vincular: int, inexistentes: int} */
    private static function resumenVacio(): array
    {
        return ['actualizados' => 0, 'sin_cambios' => 0, 'ilimitados' => 0, 'sin_vincular' => 0, 'inexistentes' => 0];
    }
}
