<?php

namespace Tests\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Un «Sistema Central» de mentira detrás de `Http::fake`, con estado: el cliente REAL del paquete (rukoz/core-client 0.1.1,
 * con su idempotencia, su circuito y sus compensaciones) habla con esto.
 *
 *  - `POST /pedidos`, `POST /pedidos/{ref}/cancelar`, `POST /pedidos/{ref}/confirmar`, `GET /pedidos/{ref}`, `GET /referencias`, `GET /stock` y `GET /ping`.
 *  - Cada body que recibe se valida contra el contrato (`ContratoPedidoCentral`, reglas de `PedidoWebRequest`): lo que no
 *    cumple se contesta 422 y queda en `$erroresDeContrato`, que los tests exigen vacío.
 *  - Lleva la cuenta del stock: `disponible` por ítem menos lo reservado por los pedidos vivos. Un pedido que no alcanza da
 *    `SIN_STOCK` (409) sin reservar nada.
 *  - `$caido = true` simula un timeout (el cliente lo trata como Central no disponible).
 *  - Mismo `external_ref`: mismo contenido → 200 con el pedido existente; distinto → 409 `PEDIDO_YA_EXISTE`.
 */
class CentralDePrueba
{
    /** @var array<string, array{payload: array, version: int, estado: string, motivo: ?string, punto: ?string, retenido: bool}> */
    public array $pedidos = [];

    /** Disponible inicial por ítem (`variante:25` => 3). Lo que no está acá es ilimitado. @var array<string, int> */
    public array $disponible = [];

    /** Lo que contesta `GET /stock` (`variante:25` => ['disponible' => 0, 'a_pedido' => false, ...]). @var array<string, array<string, mixed>> */
    public array $stock = [];

    public bool $caido = false;

    /** Si está, todo `POST /pedidos` contesta esto: `[http, body]` (por ejemplo un fixture de error del contrato). @var array{0: int, 1: array}|null */
    public ?array $errorForzado = null;

    /** @var list<string> */
    public array $erroresDeContrato = [];

    /** @var list<array{0: string, 1: string, 2: array}> [método, path, body] */
    public array $llamadas = [];

    public int $versionDeStock = 100;

    /** Se llama justo después de reservar un pedido nuevo (`fn (string $ref)`): sirve para simular una carrera. */
    public ?\Closure $alCrear = null;

    /** Vendedores de la web que el Central ya tiene asignados a un usuario suyo (`GET /referencias` → `vendedores[].id`). @var list<string> */
    public array $vendedoresAsignados = ['7'];

    /** Si es true, `confirmar` contesta 409 CONFIRMACION_RECHAZADA (por ejemplo, el contenido cambió). */
    public bool $rechazarConfirmacion = false;

    /** Si es true, `confirmar` PROCESA la venta pero la respuesta se pierde (timeout): sirve para probar el reintento idéntico. */
    public bool $perderRespuestaDeConfirmar = false;

    /** Lo que contesta `GET /referencias` (por defecto, el fixture oficial). @var array<string, mixed>|null */
    public ?array $referencias = null;

    /** Body de cada `confirmar` aceptado, por ref (para decidir si repetirlo es idempotente o PEDIDO_YA_CONFIRMADO). @var array<string, array> */
    public array $confirmaciones = [];

    /** Lo que el Central le suma a cada venta confirmada (`numero_venta`). */
    private int $ventas = 0;

    public static function instalar(): self
    {
        config([
            'rukoz.habilitada' => true,
            'rukoz.url' => 'https://central.test',
            'rukoz.api_key' => 'rk_0123456789abcdef0123456789abcdef',
            'rukoz.secret' => 'secreto-de-prueba',
            'rukoz.reintentos' => 0,
            'integracion.stock' => true,
            'integracion.checkout' => true,
            'integracion.catalogo' => false,
            'cache.default' => 'array',
        ]);

        $central = new self;
        Http::preventStrayRequests();
        Http::fake(fn (Request $request) => $central->atender($request));

        return $central;
    }

    /** @return list<array{0: string, 1: string, 2: array}> */
    public function llamadasA(string $metodo, string $ruta): array
    {
        return array_values(array_filter($this->llamadas, fn ($l) => $l[0] === $metodo && preg_match($ruta, $l[1]) === 1));
    }

    /** Los pedidos que siguen reservando stock. @return array<string, array> */
    public function vivos(): array
    {
        return array_filter($this->pedidos, fn ($p) => $p['estado'] === 'reservado');
    }

    public function reservado(string $tipo, string|int $id, ?string $salvo = null): int
    {
        $total = 0;

        foreach ($this->vivos() as $ref => $pedido) {
            if ($ref === $salvo) {
                continue;
            }

            $total += $this->cantidades($pedido['payload'])[$tipo.':'.$id] ?? 0;
        }

        return $total;
    }

    public function disponibleAhora(string $tipo, string|int $id): ?int
    {
        $clave = $tipo.':'.$id;

        return isset($this->disponible[$clave]) ? $this->disponible[$clave] - $this->reservado($tipo, $id) : null;
    }

    /** @return mixed */
    public function atender(Request $request)
    {
        $metodo = $request->method();
        $ruta = preg_replace('#^.*?/api/v1#', '', parse_url($request->url(), PHP_URL_PATH) ?: '') ?: '/';
        $body = $request->data();
        $this->llamadas[] = [$metodo, $ruta, is_array($body) ? $body : []];

        if ($this->caido) {
            throw new ConnectionException('cURL error 28: Operation timed out');
        }

        if ($metodo === 'GET' && $ruta === '/ping') {
            return Http::response(['ok' => true, 'integracion' => ['nombre' => 'Chispas Frías (prueba)', 'slug' => 'chispas-frias'], 'hora_servidor' => now()->toIso8601String()]);
        }

        if ($metodo === 'GET' && str_starts_with($ruta, '/referencias')) {
            return $this->responderReferencias($request);
        }

        if ($metodo === 'GET' && preg_match('#^/pedidos/([^/]+)$#', $ruta, $m)) {
            return isset($this->pedidos[urldecode($m[1])])
                ? Http::response($this->respuesta(urldecode($m[1])))
                : $this->error('PEDIDO_INEXISTENTE', 404, 'No existe ese pedido.');
        }

        if ($metodo === 'POST' && preg_match('#^/pedidos/([^/]+)/confirmar$#', $ruta, $m)) {
            return $this->confirmar(urldecode($m[1]), $body);
        }

        if ($metodo === 'GET' && str_starts_with($ruta, '/stock')) {
            return $this->responderStock($request);
        }

        if ($this->errorForzado !== null && $metodo === 'POST' && str_starts_with($ruta, '/pedidos')) {
            return Http::response($this->errorForzado[1], $this->errorForzado[0]);
        }

        if ($metodo === 'POST' && $ruta === '/pedidos') {
            return $this->crear($body);
        }

        if ($metodo === 'POST' && preg_match('#^/pedidos/([^/]+)/cancelar$#', $ruta, $m)) {
            return $this->cancelar(urldecode($m[1]), $body);
        }

        return $this->error('RUTA_INEXISTENTE', 404, "No hay nada en {$metodo} {$ruta}");
    }

    private function crear(array $body)
    {
        $errores = $this->errores($body);

        if ($errores !== []) {
            return $this->validacion($errores);
        }

        $ref = (string) $body['external_ref'];

        if ($desconocido = $this->puntoDesconocido($body['punto'] ?? null)) {
            return $desconocido;
        }

        if (isset($this->pedidos[$ref])) {
            return $this->normalizar($this->pedidos[$ref]['payload']) === $this->normalizar($body)
                ? Http::response($this->respuesta($ref), 200)
                : $this->error('PEDIDO_YA_EXISTE', 409, 'Ya existe un pedido con ese external_ref y otro contenido.');
        }

        if (($faltan = $this->faltantes($body, null)) !== []) {
            return $this->sinStock($faltan);
        }

        $this->pedidos[$ref] = ['payload' => $body, 'version' => 1, 'estado' => 'reservado', 'motivo' => null, 'punto' => $body['punto'] ?? $this->puntoPredeterminado(), 'retenido' => false];

        if ($this->alCrear !== null) {
            ($this->alCrear)($ref);
        }

        return Http::response($this->respuesta($ref), 201);
    }

    private function cancelar(string $ref, array $body)
    {
        if (! isset($this->pedidos[$ref])) {
            return $this->error('PEDIDO_INEXISTENTE', 404, 'No existe ese pedido.');
        }

        if (! in_array($this->pedidos[$ref]['estado'], ['reservado', 'cancelado'], true)) {
            return $this->error('ANULAR_DESDE_CENTRAL', 409, 'El pedido ya es una venta: se anula desde el Central.');
        }

        if ($this->pedidos[$ref]['estado'] === 'reservado') {
            $this->pedidos[$ref]['estado'] = 'cancelado';
            $this->pedidos[$ref]['motivo'] = $body['motivo'] ?? null;
            $this->pedidos[$ref]['version']++;
        }

        return Http::response($this->respuesta($ref), 200);
    }

    private function responderReferencias(Request $request)
    {
        $referencias = $this->referencias ?? json_decode((string) file_get_contents(base_path('tests/fixtures/rukoz/response-referencias.json')), true);
        $referencias['vendedores'] = array_map(fn ($id) => ['id' => $id, 'external_id' => $id, 'nombre' => 'Vendedor '.$id], $this->vendedoresAsignados);
        $referencias['hash'] = hash('sha256', json_encode($referencias));

        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $consulta);

        return Http::response(($consulta['hash'] ?? null) === $referencias['hash'] ? ['hash' => $referencias['hash'], 'sin_cambios' => true] : $referencias);
    }

    private function confirmar(string $ref, array $body)
    {
        if (! isset($this->pedidos[$ref])) {
            return $this->error('PEDIDO_INEXISTENTE', 404, 'No existe ese pedido.');
        }

        $pedido = $this->pedidos[$ref];

        if ($desconocido = $this->puntoDesconocido($body['punto'] ?? null)) {
            return $desconocido;
        }

        // El Central valida la forma; los errores de contrato de la web quedan anotados y los tests los exigen vacíos.
        $total = (float) $pedido['payload']['total'] + (float) ($body['envio']['costo_cobrado'] ?? 0);
        $errores = ContratoConfirmacionCentral::errores($body, $this->referencias ?? json_decode((string) file_get_contents(base_path('tests/fixtures/rukoz/response-referencias.json')), true), $total);

        if ($errores !== []) {
            array_push($this->erroresDeContrato, ...array_map(fn ($e) => 'confirmar: '.$e, $errores));

            return $this->validacion($errores);
        }

        if ($pedido['estado'] === 'cancelado') {
            return $this->error('PEDIDO_CANCELADO', 409, 'El pedido está cancelado.');
        }

        if ($pedido['estado'] !== 'reservado') {
            return $this->huella($this->confirmaciones[$ref] ?? []) === $this->huella($body)
                ? Http::response($this->respuesta($ref), 200)
                : $this->error('PEDIDO_YA_CONFIRMADO', 409, 'El pedido ya está confirmado con otro contenido.');
        }

        if (! in_array($body['vendedor']['id'], $this->vendedoresAsignados, true)) {
            return $this->error('VENDEDOR_NO_MAPEADO', 422, 'El vendedor «'.$body['vendedor']['id'].'» de la web todavía no está asignado a un usuario del Central.', ['vendedor' => $body['vendedor']]);
        }

        if ($this->rechazarConfirmacion) {
            return $this->error('CONFIRMACION_RECHAZADA', 409, 'La venta no se pudo crear: el contenido del pedido cambió.');
        }

        $pagado = array_sum(array_map(fn ($p) => (float) $p['monto'], $body['pagos'] ?? []));

        $this->pedidos[$ref] = [
            ...$pedido,
            'estado' => 'confirmado',
            'version' => $pedido['version'] + 1,
            // La venta toma el punto de la confirmación; si no, el del pedido. La retención solo rige con saldo.
            'punto' => $body['punto'] ?? $pedido['punto'],
            'retenido' => (bool) ($body['solo_con_pago_completo'] ?? false) && $total - $pagado > 0.004,
            'venta' => [
                'numero_venta' => 'TST-'.str_pad((string) ++$this->ventas, 6, '0', STR_PAD_LEFT),
                'total' => $total,
                'pagado' => $pagado,
                'saldo' => $total - $pagado,
                'confirmado_at' => $body['confirmado_at'] ?? now()->toIso8601String(),
                'empaquetado_at' => null,
                'despachado_at' => null,
                'retirado_at' => null,
                'anulado_at' => null,
                'motivo' => null,
            ],
        ];
        $this->confirmaciones[$ref] = $body;

        if ($this->perderRespuestaDeConfirmar) {
            throw new ConnectionException('cURL error 28: Operation timed out');
        }

        return Http::response($this->respuesta($ref), 201);
    }

    /** Lo que importa del body para saber si repetirlo es «lo mismo» (la huella ignora confirmado_at, fechas y email/nombre del vendedor). */
    private function huella(array $body): string
    {
        unset($body['confirmado_at']);
        $body['pagos'] = array_map(fn ($p) => array_diff_key($p, ['fecha' => 1]), $body['pagos'] ?? []);
        $body['vendedor'] = ['id' => $body['vendedor']['id'] ?? null];

        return hash('sha256', json_encode($body));
    }

    /**
     * Mueve el pedido a un estado nuevo DESDE EL CENTRAL (empaquetar, despachar, anular…) y devuelve el `datos` del evento
     * `pedido.*` tal como lo mandaría el webhook (estado completo, `version` nueva). Los tests lo meten en el paquete o lo
     * disparan como `PedidoActualizado`.
     *
     * @param  array<string, mixed>  $venta  Campos a cambiar de la venta (`despachado_at`, `numero_seguimiento`, `motivo`…).
     * @return array<string, mixed>
     */
    public function avanzar(string $ref, string $estado, array $venta = []): array
    {
        $this->pedidos[$ref]['estado'] = $estado;
        $this->pedidos[$ref]['version']++;
        $this->pedidos[$ref]['venta'] = [...($this->pedidos[$ref]['venta'] ?? []), ...$venta];

        return $this->datosDeEvento($ref);
    }

    /**
     * El pago se completa en el Central: la venta se libera sola y llega un `pedido.pago_actualizado` con `retenido_por_pago: false`.
     *
     * @return array<string, mixed> El `datos` del evento.
     */
    public function completarPago(string $ref): array
    {
        $total = (float) ($this->pedidos[$ref]['venta']['total'] ?? 0);

        $this->pedidos[$ref]['version']++;
        $this->pedidos[$ref]['retenido'] = false;
        $this->pedidos[$ref]['venta'] = [...$this->pedidos[$ref]['venta'], 'pagado' => $total, 'saldo' => 0.0];

        return $this->datosDeEvento($ref);
    }

    /** @return list<string> Los códigos de punto de `GET /referencias`. */
    public function puntosValidos(): array
    {
        $referencias = $this->referencias ?? json_decode((string) file_get_contents(base_path('tests/fixtures/rukoz/response-referencias.json')), true);

        return array_column($referencias['puntos'] ?? [], 'codigo');
    }

    private function puntoPredeterminado(): ?string
    {
        $referencias = $this->referencias ?? json_decode((string) file_get_contents(base_path('tests/fixtures/rukoz/response-referencias.json')), true);

        return collect($referencias['puntos'] ?? [])->firstWhere('predeterminado', true)['codigo'] ?? null;
    }

    /** 422 PUNTO_DESCONOCIDO si el código no es uno de los de la integración (mismo detalle que el fixture oficial). */
    private function puntoDesconocido(mixed $punto)
    {
        if ($punto === null || in_array($punto, $this->puntosValidos(), true)) {
            return null;
        }

        return $this->error('PUNTO_DESCONOCIDO', 422, "El punto «{$punto}» no existe para esta integración. Códigos válidos: ".implode(', ', $this->puntosValidos()).'.', ['punto' => $punto, 'puntos_validos' => $this->puntosValidos()]);
    }

    /** @return array<string, mixed> */
    public function datosDeEvento(string $ref): array
    {
        $pedido = $this->pedidos[$ref];
        $venta = $pedido['venta'] ?? [];

        return [
            'pedido_id' => array_search($ref, array_keys($this->pedidos), true) + 1,
            'external_ref' => $ref,
            'estado' => $pedido['estado'],
            'version' => $pedido['version'],
            'numero_venta' => $venta['numero_venta'] ?? null,
            'venta_id' => 4000,
            'transportista' => $venta['transportista'] ?? null,
            'numero_seguimiento' => $venta['numero_seguimiento'] ?? null,
            'url_seguimiento' => $venta['url_seguimiento'] ?? null,
            'confirmado_at' => $venta['confirmado_at'] ?? null,
            'empaquetado_at' => $venta['empaquetado_at'] ?? null,
            'despachado_at' => $venta['despachado_at'] ?? null,
            'retirado_at' => $venta['retirado_at'] ?? null,
            'anulado_at' => $venta['anulado_at'] ?? null,
            'total' => number_format((float) ($venta['total'] ?? $pedido['payload']['total']), 2, '.', ''),
            'pagado' => number_format((float) ($venta['pagado'] ?? 0), 2, '.', ''),
            'saldo' => number_format((float) ($venta['saldo'] ?? 0), 2, '.', ''),
            'moneda' => 'ARS',
            'punto' => $pedido['punto'] ?? null,
            'retenido_por_pago' => (bool) ($pedido['retenido'] ?? false),
            ...(isset($venta['motivo']) ? ['motivo' => $venta['motivo']] : []),
        ];
    }

    private function responderStock(Request $request)
    {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $consulta);
        $tipo = $consulta['tipo'] ?? null;
        $ids = array_map('strval', (array) ($consulta['ids'] ?? []));

        $items = [];

        foreach ($this->stock as $clave => $item) {
            [$t, $id] = explode(':', $clave, 2);

            if (($tipo === null || $t === $tipo) && ($ids === [] || in_array($id, $ids, true))) {
                $items[] = $item + ['tipo' => $t, 'id' => $id, 'a_pedido' => false, 'vinculado' => true, 'aproximado' => false, 'opciones' => null];
            }
        }

        return Http::response(['items' => $items, 'generado_at' => now()->toIso8601String(), 'version' => $this->versionDeStock, 'hay_mas' => false, 'siguiente_cursor' => null]);
    }

    /** @return list<string> */
    private function errores(array $body): array
    {
        $errores = ContratoPedidoCentral::errores($body);

        if ($errores !== []) {
            array_push($this->erroresDeContrato, ...$errores);
        }

        return $errores;
    }

    /**
     * Las líneas que no alcanzan.
     *
     * @return list<array<string, mixed>>
     */
    private function faltantes(array $body, ?string $salvo): array
    {
        $faltan = [];
        $pedidoPorClave = $this->cantidades($body);

        foreach ($body['items'] as $indice => $item) {
            $claves = $item['tipo'] === 'combo' ? $this->clavesDeCombo($item) : [$item['tipo'].':'.$item['id'] => (int) $item['cantidad']];

            foreach ($claves as $clave => $cantidad) {
                if (! isset($this->disponible[$clave])) {
                    continue;
                }

                [$tipo, $id] = explode(':', $clave, 2);
                $libre = $this->disponible[$clave] - $this->reservado($tipo, $id, $salvo);
                $total = $pedidoPorClave[$clave] ?? $cantidad;

                if ($total > $libre) {
                    $faltan[$indice] = [
                        'indice' => $indice,
                        'tipo' => $item['tipo'],
                        'id' => $item['id'],
                        'nombre' => $item['nombre'] ?? '',
                        'pedido' => (int) $item['cantidad'],
                        'disponible' => max(0, $libre),
                        'faltan' => [['articulo_id' => 1, 'rukoz_sku' => 'RKZ-0001', 'nombre' => $item['nombre'] ?? '', 'necesario' => $total, 'disponible' => max(0, $libre)]],
                    ];
                }
            }
        }

        return array_values($faltan);
    }

    /** Cantidad por ítem físico que pide un pedido (los combos aportan sus opciones elegidas). @return array<string, int> */
    private function cantidades(array $payload): array
    {
        $total = [];

        foreach ($payload['items'] ?? [] as $item) {
            $claves = $item['tipo'] === 'combo' ? $this->clavesDeCombo($item) : [$item['tipo'].':'.$item['id'] => (int) $item['cantidad']];

            foreach ($claves as $clave => $cantidad) {
                $total[$clave] = ($total[$clave] ?? 0) + $cantidad;
            }
        }

        return $total;
    }

    /** @return array<string, int> */
    private function clavesDeCombo(array $item): array
    {
        // El combo tiene su propio disponible (`combo:ID`) además del de las opciones que lleva.
        $claves = ['combo:'.$item['id'] => (int) $item['cantidad']];

        foreach ($item['selecciones'] ?? [] as $seleccion) {
            foreach ($seleccion['opciones'] as $opcion) {
                $clave = $opcion['tipo'].':'.$opcion['id'];
                $claves[$clave] = ($claves[$clave] ?? 0) + (int) ($opcion['cantidad'] ?? 1) * (int) $item['cantidad'];
            }
        }

        return $claves;
    }

    /** @param list<array<string, mixed>> $faltan */
    private function sinStock(array $faltan)
    {
        return $this->error('SIN_STOCK', 409, 'No hay stock suficiente. No se reservó nada.', ['items' => $faltan]);
    }

    /** @param list<string> $errores */
    private function validacion(array $errores)
    {
        return $this->error('VALIDACION', 422, 'Datos inválidos: '.implode(' | ', $errores), ['errores' => $errores]);
    }

    private function error(string $codigo, int $http, string $mensaje, array $detalle = [])
    {
        return Http::response(['error' => ['codigo' => $codigo, 'mensaje' => $mensaje, 'detalle' => (object) $detalle]], $http);
    }

    private function respuesta(string $ref): array
    {
        $pedido = $this->pedidos[$ref];
        $payload = $pedido['payload'];
        $venta = isset($pedido['venta']) ? $this->datosDeEvento($ref) : [];

        return [
            ...$venta,
            'pedido_id' => array_search($ref, array_keys($this->pedidos), true) + 1,
            'external_ref' => $ref,
            'estado' => $pedido['estado'],
            'version' => $pedido['version'],
            'total' => $venta['total'] ?? number_format((float) $payload['total'], 2, '.', ''),
            'punto' => $pedido['punto'] ?? null,
            'retenido_por_pago' => (bool) ($pedido['retenido'] ?? false),
            'total_con_recargo' => isset($payload['total_con_recargo']) ? number_format((float) $payload['total_con_recargo'], 2, '.', '') : null,
            'moneda' => $payload['moneda'] ?? 'ARS',
            'items' => array_map(fn ($i) => [
                'tipo' => $i['tipo'], 'id' => $i['id'], 'nombre' => $i['nombre'] ?? '', 'cantidad' => $i['cantidad'],
                'precio_unitario' => number_format((float) $i['precio_unitario'], 2, '.', ''), 'vinculado' => true, 'motivo_no_vinculado' => null, 'resuelto' => [],
            ], $payload['items']),
            'reservas' => [],
            'cancelado_at' => $pedido['estado'] === 'cancelado' ? now()->toIso8601String() : null,
            'motivo_cancelacion' => $pedido['motivo'],
        ];
    }

    private function normalizar(array $payload): string
    {
        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
