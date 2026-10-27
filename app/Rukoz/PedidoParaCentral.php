<?php

namespace App\Rukoz;

use App\Models\Combo;
use App\Models\ComboItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Setting;
use App\Support\Provincias;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Arma el body de `POST /pedidos` del Central a partir de un pedido de la web (guardado o todavía en memoria: el checkout
 * lo arma ANTES de reservar, con modelos sin guardar).
 *
 *  - Líneas: un producto/variante vinculado va como `tipo:id` (con variantes activas, siempre la variante); el
 *    `precio_unitario` es el FINAL por unidad (escalas, ofertas, recargo de la variante y add-ons) y NO incluye el cupón.
 *  - Cupón: no se reparte entre las líneas. `total` = suma de las líneas − descuento; el Central toma esa diferencia
 *    como descuento al confirmar (API.md, «El descuento no se manda dos veces»): al confirmar no se manda otra vez.
 *  - Combo fijo → el combo, sin selecciones. Combo con elección (o mixto) → `selecciones`, un grupo por componente de la
 *    receta, con el nombre que informó el catálogo (`CatalogoDeChispasFrias::nombreDeGrupo`) y lo elegido en el snapshot
 *    del pedido (`order_items.combo_selections`).
 *  - `nota` por línea (hasta 500 caracteres): el color a elección, el texto de cada add-on y, en un combo, el color a
 *    elección de cada componente. Puede llevar un nombre: no se loguea en ningún lado.
 *  - Envío: no es una línea (el Central solo admite producto/variante/combo) ni suma al `total`; el costo se informa al
 *    confirmar. Que el pedido califica para envío gratis (la API no tiene campo para eso) viaja al principio de
 *    `cliente.observaciones`.
 *  - Punto de atención: no se manda. La web tiene uno solo y el Central usa el predeterminado de la integración.
 *  - Los productos «con variantes» (`no_aplica` en el Central) nunca viajan como línea: lo que se compra es una variante.
 */
final class PedidoParaCentral
{
    private const MAX_NOTA = 500;

    /**
     * @param  iterable<OrderItem>  $items
     */
    public function armar(Order $pedido, iterable $items, CarbonInterface $creadoAt): PedidoArmado
    {
        $lineas = [];
        $etiquetas = [];

        foreach ($items as $item) {
            [$linea, $etiqueta] = $item->combo_id !== null
                ? $this->lineaDeCombo($item)
                : $this->lineaDeProducto($item);

            $lineas[] = $linea;
            $etiquetas[] = $etiqueta;
        }

        $payload = [
            'cliente' => $this->cliente($pedido),
            'items' => $lineas,
            'total' => (float) $pedido->total,
            'moneda' => 'ARS',
            'creado_at' => $creadoAt->toIso8601String(),
        ];

        if ($pedido->total_with_surcharge !== null) {
            $payload['total_con_recargo'] = (float) $pedido->total_with_surcharge;
        }

        return new PedidoArmado($payload, $etiquetas);
    }

    /** La nota de cualquier línea del pedido (producto, variante o combo), tal como viaja al Central y se muestra al confirmar. */
    public static function notaDe(OrderItem $item): ?string
    {
        return $item->combo_id !== null ? self::notaDeCombo($item) : self::notaDeProducto($item);
    }

    /**
     * La nota de una línea de producto: «Color: Turquesa · Grabado: Para Ana». `null` si no hay nada que decir.
     */
    public static function notaDeProducto(OrderItem $item): ?string
    {
        $partes = [];

        $color = trim((string) $item->custom_color_text);

        if ($color !== '') {
            $partes[] = 'Color: '.$color;
        }

        foreach ((array) $item->addons_selected as $addon) {
            $texto = trim((string) ($addon['custom_text'] ?? ''));

            if ($texto !== '') {
                $partes[] = trim((string) ($addon['name'] ?? 'Personalización')).': '.$texto;
            }
        }

        return self::nota($partes);
    }

    /**
     * La nota de un combo: el color a elección de cada componente, «Pistola: Color: Turquesa». Los colores de la lista
     * (una variante concreta) ya viajan como la variante de la selección: acá solo va lo que el Central no puede saber.
     */
    public static function notaDeCombo(OrderItem $item): ?string
    {
        $partes = [];

        foreach ((array) $item->combo_selections as $componente) {
            $color = trim((string) ($componente['custom_color_text'] ?? ''));

            if ($color !== '') {
                $partes[] = trim((string) ($componente['product_title'] ?? 'Componente')).': Color: '.$color;
            }
        }

        return self::nota($partes);
    }

    /** @param list<string> $partes */
    private static function nota(array $partes): ?string
    {
        $nota = implode(' · ', $partes);

        return $nota === '' ? null : mb_substr($nota, 0, self::MAX_NOTA);
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function lineaDeProducto(OrderItem $item): array
    {
        if ($item->product_id === null && $item->product_variant_id === null) {
            throw ValidationException::withMessages([
                'pedido' => "\"{$item->product_title}\" ya no existe en el catálogo: quitalo del carrito para poder pedirlo.",
            ]);
        }

        $conVariante = $item->product_variant_id !== null;
        $nombre = $item->product_title.($conVariante && $item->variant_name ? ' ('.$item->variant_name.')' : '');

        $linea = [
            'tipo' => $conVariante ? 'variante' : 'producto',
            'id' => (string) ($conVariante ? $item->product_variant_id : $item->product_id),
            'nombre' => Str::limit($nombre, 255, ''),
            'cantidad' => (int) $item->cantidad,
            'precio_unitario' => (float) $item->precio_unitario,
        ];

        if (($nota = self::notaDeProducto($item)) !== null) {
            $linea['nota'] = $nota;
        }

        return [$linea, [
            'titulo' => (string) $item->product_title,
            'variante' => $conVariante ? $item->variant_name : null,
            'product_id' => $item->product_id,
            'combo_id' => null,
            'cantidad' => (int) $item->cantidad,
            'stock' => [[$linea['tipo'], $linea['id']]],
        ]];
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function lineaDeCombo(OrderItem $item): array
    {
        $combo = Combo::with(['items.product.variantsActive'])->find($item->combo_id);

        if ($combo === null) {
            throw ValidationException::withMessages([
                'pedido' => "El combo \"{$item->product_title}\" ya no existe en el catálogo: quitalo del carrito para poder pedirlo.",
            ]);
        }

        $linea = [
            'tipo' => 'combo',
            'id' => (string) $combo->id,
            'nombre' => Str::limit($combo->title, 255, ''),
            'cantidad' => (int) $item->cantidad,
            'precio_unitario' => (float) $item->precio_unitario,
        ];

        // Misma regla que el catálogo (CatalogoDeChispasFrias::comboComoItem): con algún componente con color a elegir el
        // combo es de `seleccion` y hay que decir qué se eligió; si no, es `fijo` y el Central lo resuelve solo.
        if ($combo->items->contains(fn (ComboItem $componente) => CatalogoDeChispasFrias::requiereSeleccionDeColor($componente))) {
            $linea['selecciones'] = $this->seleccionesDe($combo, $item);
        }

        if (($nota = self::notaDeCombo($item)) !== null) {
            $linea['nota'] = $nota;
        }

        return [$linea, [
            'titulo' => (string) $item->product_title,
            'variante' => null,
            'product_id' => null,
            'combo_id' => $combo->id,
            'cantidad' => (int) $item->cantidad,
            // El combo y cada componente elegido: lo que hay que releer del Central si no alcanza el stock.
            'stock' => [
                ['combo', (string) $combo->id],
                ...collect((array) $item->combo_selections)
                    ->map(fn (array $c) => ! empty($c['product_variant_id'])
                        ? ['variante', (string) $c['product_variant_id']]
                        : ['producto', (string) $c['product_id']])
                    ->all(),
            ],
        ]];
    }

    /**
     * Un grupo por componente de la receta —el mismo agrupamiento que manda el catálogo— con lo que quedó guardado en el
     * snapshot del pedido. Un componente es único por producto dentro del combo (`unique(combo_id, product_id)`), así que
     * el producto del snapshot identifica el grupo.
     *
     * @return list<array{grupo: string, opciones: list<array{tipo: string, id: string, cantidad: int}>}>
     */
    private function seleccionesDe(Combo $combo, OrderItem $item): array
    {
        $elegidos = collect((array) $item->combo_selections)->keyBy('product_id');
        $selecciones = [];

        foreach ($combo->items as $componente) {
            $elegido = $elegidos->get($componente->product_id);

            if ($elegido === null) {
                throw ValidationException::withMessages([
                    'pedido' => "El combo \"{$item->product_title}\" cambió desde que lo agregaste al carrito: quitalo y volvé a agregarlo.",
                ]);
            }

            $varianteId = $elegido['product_variant_id'] ?? null;

            $selecciones[] = [
                'grupo' => CatalogoDeChispasFrias::nombreDeGrupo($componente),
                'opciones' => [[
                    'tipo' => $varianteId !== null ? 'variante' : 'producto',
                    'id' => (string) ($varianteId ?? $elegido['product_id']),
                    'cantidad' => (int) $elegido['quantity'],
                ]],
            ];
        }

        return $selecciones;
    }

    /**
     * Los topes son los del Central (`PedidoWebRequest`): la web admite textos más largos y el Central los rechazaría.
     * Nombre y apellido viajan juntos en `nombre`; la provincia, con su nombre (no con el código interno). Con el checkout mínimo DNI, localidad, código postal, email y provincia (en un retiro) pueden faltar: simplemente no viajan.
     *
     * @return array<string, string>
     */
    private function cliente(Order $pedido): array
    {
        $observaciones = array_filter([
            $this->enviaGratis($pedido) ? 'Envío gratis' : null,
            $pedido->discount_code !== null ? 'Cupón: '.$pedido->discount_code : null,
            trim((string) $pedido->observations) !== '' ? trim((string) $pedido->observations) : null,
        ]);

        $direccion = trim(implode(' ', array_filter([
            trim((string) $pedido->address),
            trim((string) $pedido->number),
        ])));

        $cliente = [
            'nombre' => $this->recortar($pedido->nombreCompleto(), 255),
            'telefono' => $this->recortar($pedido->phone, 60),
            'dni' => $this->recortar($pedido->dni, 30),
            'email' => $this->recortar($pedido->email, 255),
            'direccion' => $this->recortar($direccion, 500),
            'localidad' => $this->recortar($pedido->city, 255),
            'provincia' => $this->recortar(Provincias::nombre($pedido->province), 100),
            'cp' => $this->recortar($pedido->postal_code, 10),
            'observaciones' => $this->recortar(implode("\n", $observaciones), 2000),
        ];

        return array_filter($cliente, fn ($valor) => $valor !== null && $valor !== '');
    }

    /** Envío gratis por combo (guardado en el pedido) o por haber llegado al umbral de la tienda. */
    private function enviaGratis(Order $pedido): bool
    {
        if ($pedido->free_shipping) {
            return true;
        }

        $umbral = (float) (Setting::get('free_shipping_threshold') ?? 0);

        return $umbral > 0 && (float) $pedido->subtotal >= $umbral;
    }

    private function recortar(?string $valor, int $max): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : mb_substr($valor, 0, $max);
    }
}
