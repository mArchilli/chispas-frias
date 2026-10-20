<?php

namespace Tests\Feature\Rukoz;

use App\Enums\EstadoOrden;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\Product;
use App\Rukoz\EstadoDesdeCentral;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Rukoz\CoreClient\Eventos\PedidoActualizado;
use Rukoz\CoreClient\Inbox\RegistroDeInbox;
use Rukoz\CoreClient\Procesamiento\ProcesadorEventos;
use Tests\Support\PedidosConCentral;
use Tests\TestCase;

/**
 * 10.4 — Los estados que llegan del Central (`pedido.*`) se reflejan en el pedido de la web. Los eventos pasan por el camino
 * real del paquete (inbox → regla de `version` → `PedidoActualizado` → listener), con los `datos` de los fixtures oficiales del
 * Central (contrato 0.1.2) y los que arma el Central falso al mover una venta.
 */
class EstadosDesdeCentralTest extends TestCase
{
    use PedidosConCentral;
    use RefreshDatabase;

    private int $secuencia = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararCentral();
    }

    protected function tearDown(): void
    {
        $this->exigirContratoSinErrores();

        parent::tearDown();
    }

    /** Entrega un evento como lo haría el webhook o el feed: lo guarda en el inbox y lo procesa. */
    private function entregar(string $evento, array $datos, ?string $id = null): void
    {
        $sobre = ['id' => $id ?? (string) Str::uuid(), 'evento' => $evento, 'secuencia' => ++$this->secuencia, 'creado_at' => now()->toIso8601String(), 'integracion' => 'chispas-frias', 'datos' => $datos];

        [$fila] = app(RegistroDeInbox::class)->guardar($sobre, json_encode($sobre), 'webhook');
        app(ProcesadorEventos::class)->procesar([$fila]);
    }

    /** El `datos` de un fixture oficial, apuntado a nuestro pedido. */
    private function fixture(string $nombre, Order $pedido, array $cambios = []): array
    {
        $datos = json_decode((string) file_get_contents(base_path("tests/fixtures/rukoz/webhook-pedido-{$nombre}.json")), true)['datos'];

        return [...$datos, 'external_ref' => $pedido->rukoz_ref, ...$cambios];
    }

    // ------------------------------------------------------------------------------------------------------------------
    // Cada evento
    // ------------------------------------------------------------------------------------------------------------------

    public function test_confirmar_desde_el_central_se_refleja_en_la_web(): void
    {
        $pedido = $this->comprar();

        $this->entregar('pedido.confirmado', $this->fixture('confirmado', $pedido, ['version' => 2]));

        $pedido->refresh();
        $this->assertSame(EstadoOrden::Confirmado, $pedido->estado);
        $this->assertSame('confirmado', $pedido->rukoz_estado);
        $this->assertSame('CHF-000045', $pedido->rukoz_numero_venta);
        $this->assertSame('2026-10-20 14:05:00', $pedido->confirmado_at->utc()->toDateTimeString());
        $this->assertSame(53500.0, (float) $pedido->rukoz_total_venta);
        $this->assertSame(33500.0, (float) $pedido->rukoz_saldo);
        $this->assertSame('Correo Argentino', $pedido->rukoz_transportista);
        $this->assertNull($pedido->confirmado_por, 'Lo confirmó el Central, no un usuario de la web');
        $this->assertTrue($pedido->estaConfirmado());
        $this->assertFalse($pedido->puedeTransicionarA(EstadoOrden::Cancelado));
    }

    public function test_empaquetado_despachado_con_seguimiento_y_entregado(): void
    {
        $pedido = $this->confirmado();
        $v = $pedido->rukoz_version;

        $this->entregar('pedido.empaquetado', $this->fixture('empaquetado', $pedido, ['version' => $v + 1]));
        $this->assertSame(EstadoOrden::Empaquetado, $pedido->fresh()->estado);
        $this->assertNotNull($pedido->fresh()->rukoz_empaquetado_at);

        $this->entregar('pedido.despachado', $this->fixture('despachado', $pedido, ['version' => $v + 2, 'numero_seguimiento' => 'AR123', 'url_seguimiento' => 'https://seguimiento.test/AR123']));
        $pedido->refresh();
        $this->assertSame(EstadoOrden::Despachado, $pedido->estado);
        $this->assertSame('2026-10-20 20:30:00', $pedido->despachado_at->utc()->toDateTimeString());
        $this->assertSame('AR123', $pedido->rukoz_numero_seguimiento);
        $this->assertSame('https://seguimiento.test/AR123', $pedido->rukoz_url_seguimiento);

        $this->entregar('pedido.retirado', $this->fixture('retirado', $pedido, ['version' => $v + 3]));
        $pedido->refresh();
        $this->assertSame(EstadoOrden::Entregado, $pedido->estado);
        $this->assertNotNull($pedido->rukoz_retirado_at);
        $this->assertSame(0.0, (float) $pedido->rukoz_saldo);
    }

    public function test_el_envio_y_el_pago_actualizados_refrescan_seguimiento_y_saldo_sin_cambiar_el_estado(): void
    {
        $pedido = $this->confirmado();
        $v = $pedido->rukoz_version;
        $this->entregar('pedido.despachado', $this->fixture('despachado', $pedido, ['version' => $v + 1]));

        $this->entregar('pedido.envio_actualizado', $this->fixture('envio-actualizado', $pedido, ['version' => $v + 2, 'estado' => 'despachado']));
        $this->assertSame('AR123456789', $pedido->fresh()->rukoz_numero_seguimiento);
        $this->entregar('pedido.pago_actualizado', $this->fixture('pago-actualizado', $pedido, ['version' => $v + 3, 'estado' => 'despachado']));

        $pedido->refresh();
        $this->assertSame(EstadoOrden::Despachado, $pedido->estado);
        $this->assertSame(0.0, (float) $pedido->rukoz_saldo);
        $this->assertSame(53500.0, (float) $pedido->rukoz_pagado);
    }

    public function test_cargar_el_pago_completo_en_el_central_libera_la_retencion(): void
    {
        $pedido = $this->comprar();
        $this->confirmar($pedido, ['solo_con_pago_completo' => true])->assertSessionHasNoErrors();
        $pedido->refresh();
        $this->assertTrue($pedido->rukoz_retenido_por_pago);
        $this->assertSame(7000.0, (float) $pedido->rukoz_saldo);

        // El pago se completa en el Central: la venta se libera sola y llega `pedido.pago_actualizado` con `retenido_por_pago: false`.
        $this->entregar('pedido.pago_actualizado', $this->central->completarPago($pedido->rukoz_ref));

        $pedido->refresh();
        $this->assertFalse($pedido->rukoz_retenido_por_pago);
        $this->assertSame(0.0, (float) $pedido->rukoz_saldo);
        $this->assertSame(7000.0, (float) $pedido->rukoz_pagado);
        $this->assertSame(EstadoOrden::Confirmado, $pedido->estado);
    }

    public function test_un_estado_sin_retenido_por_pago_no_borra_lo_ya_sabido(): void
    {
        $pedido = $this->comprar();
        $this->confirmar($pedido, ['solo_con_pago_completo' => true])->assertSessionHasNoErrors();
        $v = $pedido->fresh()->rukoz_version;

        $datos = $this->fixture('empaquetado', $pedido, ['version' => $v + 1]);
        unset($datos['retenido_por_pago']);
        $this->entregar('pedido.empaquetado', $datos);

        $this->assertSame(EstadoOrden::Empaquetado, $pedido->fresh()->estado);
        $this->assertTrue($pedido->fresh()->rukoz_retenido_por_pago);
    }

    public function test_la_venta_anulada_queda_anulada_con_el_motivo_y_libera_el_cupon_una_sola_vez(): void
    {
        $cupon = DiscountCode::create(['code' => 'DIEZ', 'percentage' => 10, 'usage_limit' => 5, 'usage_count' => 0, 'is_active' => true]);
        $pedido = $this->confirmado(2, ['cart_discount_code' => 'DIEZ']);
        $this->assertSame(1, $cupon->fresh()->usage_count);
        $v = $pedido->rukoz_version;

        $anulado = $this->fixture('anulado', $pedido, ['version' => $v + 1, 'motivo' => 'Pedido duplicado']);
        $this->entregar('pedido.anulado', $anulado);

        $pedido->refresh();
        $this->assertSame(EstadoOrden::Anulado, $pedido->estado);
        $this->assertSame('anulado', $pedido->rukoz_estado);
        $this->assertSame('Pedido duplicado', $pedido->motivo_cancelacion);
        $this->assertNotNull($pedido->rukoz_anulado_at);
        $this->assertSame(0, $cupon->fresh()->usage_count, 'El cupo del cupón vuelve');
        $this->assertNotNull($pedido->discount_usage_liberado_at);
        $this->assertFalse($pedido->puedeTransicionarA(EstadoOrden::Cancelado));

        // Se repite (otro id de evento, misma versión) y se reaplica directo: nada cambia, no se libera dos veces.
        $this->entregar('pedido.anulado', $anulado);
        app(EstadoDesdeCentral::class)->aplicar($pedido->rukoz_ref, $v + 1, 'anulado', $anulado);
        $this->assertSame(0, $cupon->fresh()->usage_count);
        $this->assertSame(EstadoOrden::Anulado, $pedido->fresh()->estado);
    }

    public function test_anular_en_el_central_no_toca_el_stock_local_porque_lo_devuelve_el_central(): void
    {
        $pedido = $this->confirmado();
        $producto = Product::query()->first();
        $this->assertSame(50, $producto->stock, 'El stock local nunca se descontó: lo lleva el Central');

        // El Central anula y publica su stock nuevo; la web lo espeja con EspejoStock (10.2).
        $this->entregar('pedido.anulado', $this->fixture('anulado', $pedido, ['version' => $pedido->rukoz_version + 1]));
        $sobre = ['id' => (string) Str::uuid(), 'evento' => 'stock.actualizado', 'secuencia' => ++$this->secuencia, 'datos' => ['completo' => false, 'version' => 500, 'items' => [['tipo' => 'producto', 'id' => (string) $producto->id, 'disponible' => 50, 'a_pedido' => false, 'vinculado' => true, 'aproximado' => false, 'opciones' => null]]]];
        [$fila] = app(RegistroDeInbox::class)->guardar($sobre, json_encode($sobre), 'webhook');
        app(ProcesadorEventos::class)->procesar([$fila]);

        $this->assertSame(50, $producto->fresh()->stock);
        $this->assertSame(EstadoOrden::Anulado, $pedido->fresh()->estado);
    }

    public function test_reserva_liberada_por_el_central_cancela_el_pedido_y_libera_el_cupon(): void
    {
        $cupon = DiscountCode::create(['code' => 'DIEZ', 'percentage' => 10, 'usage_limit' => 5, 'usage_count' => 0, 'is_active' => true]);
        $pedido = $this->comprar(2, 1000, ['cart_discount_code' => 'DIEZ']);
        $this->assertSame(1, $cupon->fresh()->usage_count);

        $this->entregar('pedido.reserva_liberada', $this->fixture('reserva-liberada', $pedido, ['version' => 2, 'motivo' => 'Sin confirmar hace 3 semanas']));

        $pedido->refresh();
        $this->assertSame(EstadoOrden::Cancelado, $pedido->estado);
        $this->assertSame('cancelado', $pedido->rukoz_estado);
        $this->assertSame('Sin confirmar hace 3 semanas', $pedido->motivo_cancelacion);
        $this->assertSame(0, $cupon->fresh()->usage_count);
    }

    public function test_volver_atras_en_el_central_limpia_las_fechas_del_estado_dejado(): void
    {
        $pedido = $this->confirmado();
        $v = $pedido->rukoz_version;
        $this->entregar('pedido.despachado', $this->fixture('despachado', $pedido, ['version' => $v + 1]));
        $this->assertNotNull($pedido->fresh()->despachado_at);

        $this->entregar('pedido.estado_revertido', $this->fixture('estado-revertido', $pedido, ['version' => $v + 2]));

        $pedido->refresh();
        $this->assertSame(EstadoOrden::Confirmado, $pedido->estado);
        $this->assertNull($pedido->despachado_at);
        $this->assertNull($pedido->rukoz_empaquetado_at);
    }

    // ------------------------------------------------------------------------------------------------------------------
    // Repetidos, desordenados y de otros
    // ------------------------------------------------------------------------------------------------------------------

    public function test_un_evento_repetido_no_cambia_nada(): void
    {
        $pedido = $this->confirmado();
        $datos = $this->fixture('despachado', $pedido, ['version' => $pedido->rukoz_version + 1]);

        $this->entregar('pedido.despachado', $datos, 'evento-1');
        $despues = $pedido->fresh()->only(['estado', 'rukoz_version', 'despachado_at', 'updated_at']);
        $this->entregar('pedido.despachado', $datos, 'evento-1'); // mismo id: lo descarta el inbox
        $this->entregar('pedido.despachado', $datos, 'evento-2'); // otro id, misma versión: lo ignora la regla de versión

        $this->assertEquals($despues, $pedido->fresh()->only(['estado', 'rukoz_version', 'despachado_at', 'updated_at']));
    }

    public function test_eventos_desordenados_gana_la_version_mas_nueva(): void
    {
        $pedido = $this->confirmado();
        $v = $pedido->rukoz_version;

        // Llega el despachado (v+2) antes que el empaquetado (v+1).
        $this->entregar('pedido.despachado', $this->fixture('despachado', $pedido, ['version' => $v + 2]));
        $this->entregar('pedido.empaquetado', $this->fixture('empaquetado', $pedido, ['version' => $v + 1]));

        $pedido->refresh();
        $this->assertSame(EstadoOrden::Despachado, $pedido->estado, 'El estado viejo no pisa al nuevo');
        $this->assertSame($v + 2, $pedido->rukoz_version);
    }

    public function test_el_listener_descarta_una_version_menor_que_la_guardada_aunque_el_paquete_no_lo_haya_hecho(): void
    {
        $pedido = $this->confirmado();
        $v = $pedido->rukoz_version;
        $this->entregar('pedido.despachado', $this->fixture('despachado', $pedido, ['version' => $v + 5]));

        $resultado = app(EstadoDesdeCentral::class)->aplicar($pedido->rukoz_ref, $v + 1, 'empaquetado', $this->fixture('empaquetado', $pedido));

        $this->assertNull($resultado);
        $this->assertSame(EstadoOrden::Despachado, $pedido->fresh()->estado);
    }

    public function test_un_evento_de_un_pedido_que_la_web_no_conoce_se_ignora(): void
    {
        $this->entregar('pedido.confirmado', [...$this->fixture('confirmado', $this->comprar()), 'external_ref' => 'OTRA-WEB-1']);

        $this->assertSame(0, Order::where('estado', EstadoOrden::Confirmado->value)->count());
    }

    public function test_un_pedido_cancelado_en_la_web_no_se_reabre_por_un_evento(): void
    {
        $pedido = $this->comprar();
        $this->cambiarEstado($pedido, 'cancelado')->assertSessionHasNoErrors();
        $this->assertSame(EstadoOrden::Cancelado, $pedido->fresh()->estado);

        $this->entregar('pedido.confirmado', $this->fixture('confirmado', $pedido, ['version' => 9]));

        $this->assertSame(EstadoOrden::Cancelado, $pedido->fresh()->estado);
        $this->assertNull($pedido->fresh()->confirmado_at);
    }

    public function test_confirmar_en_la_web_y_despues_el_evento_de_confirmado_no_duplica_ni_pisa(): void
    {
        $pedido = $this->confirmado();
        $numero = $pedido->rukoz_numero_venta;
        $porUsuario = $pedido->confirmado_por;

        // El webhook de la confirmación (misma versión que devolvió `confirmar`).
        $this->entregar('pedido.confirmado', $this->central->datosDeEvento($pedido->rukoz_ref));

        $pedido->refresh();
        $this->assertSame(EstadoOrden::Confirmado, $pedido->estado);
        $this->assertSame($numero, $pedido->rukoz_numero_venta);
        $this->assertSame($porUsuario, $pedido->confirmado_por, 'Quién confirmó no se pierde');
    }

    public function test_los_estados_que_mueve_el_central_falso_llegan_igual_que_los_reales(): void
    {
        $pedido = $this->confirmado();
        $ref = $pedido->rukoz_ref;

        $this->entregar('pedido.empaquetado', $this->central->avanzar($ref, 'empaquetado', ['empaquetado_at' => now()->toIso8601String()]));
        $this->assertSame(EstadoOrden::Empaquetado, $pedido->fresh()->estado);

        $this->entregar('pedido.despachado', $this->central->avanzar($ref, 'despachado', ['despachado_at' => now()->toIso8601String(), 'numero_seguimiento' => 'XYZ']));
        $this->assertSame(EstadoOrden::Despachado, $pedido->fresh()->estado);
        $this->assertSame('XYZ', $pedido->fresh()->rukoz_numero_seguimiento);

        $this->entregar('pedido.anulado', $this->central->avanzar($ref, 'anulado', ['anulado_at' => now()->toIso8601String(), 'motivo' => 'Error de carga']));
        $this->assertSame(EstadoOrden::Anulado, $pedido->fresh()->estado);
        $this->assertSame('Error de carga', $pedido->fresh()->motivo_cancelacion);
    }

    // ------------------------------------------------------------------------------------------------------------------
    // La ficha muestra lo que informa el Central
    // ------------------------------------------------------------------------------------------------------------------

    public function test_la_ficha_muestra_estado_pagado_saldo_retencion_y_seguimiento(): void
    {
        $pedido = $this->comprar();
        $this->confirmar($pedido, ['solo_con_pago_completo' => true, 'pagos' => [['cuenta' => 'EFECTIVO', 'monto' => 2000]]])->assertSessionHasNoErrors();
        $v = $pedido->fresh()->rukoz_version;
        $this->entregar('pedido.despachado', $this->central->avanzar($pedido->rukoz_ref, 'despachado', [
            'despachado_at' => now()->toIso8601String(),
            'transportista' => ['id' => 1, 'nombre' => 'Correo Argentino'],
            'numero_seguimiento' => 'AR999',
            'url_seguimiento' => 'https://seguimiento.test/AR999',
        ]));

        $this->actingAs($this->admin())->get(route('admin.orders.show', $pedido))
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Orders/Show')
                ->where('order.estado', 'despachado')
                ->where('order.esta_confirmado', true)
                ->where('order.puede_confirmar', false)
                ->where('order.transiciones_disponibles', [])
                ->where('order.venta.numero', 'TST-000001')
                ->where('order.venta.total', 7000)
                ->where('order.venta.pagado', 2000)
                ->where('order.venta.saldo', 5000)
                ->where('order.venta.retenido_por_pago', true)
                ->where('order.venta.seguimiento.transportista', 'Correo Argentino')
                ->where('order.venta.seguimiento.numero', 'AR999')
                ->where('order.venta.seguimiento.url', 'https://seguimiento.test/AR999')
            );

        $this->assertGreaterThan($v - 1, $pedido->fresh()->rukoz_version);
    }

    public function test_el_enlace_de_seguimiento_solo_se_ofrece_si_es_http(): void
    {
        $pedido = $this->confirmado();
        $v = $pedido->rukoz_version;
        $this->entregar('pedido.despachado', $this->fixture('despachado', $pedido, ['version' => $v + 1, 'numero_seguimiento' => 'AR1', 'url_seguimiento' => 'javascript:alert(1)']));

        $this->actingAs($this->admin())->get(route('admin.orders.show', $pedido))
            ->assertInertia(fn ($page) => $page->where('order.venta.seguimiento.numero', 'AR1')->where('order.venta.seguimiento.url', null));
    }

    // ------------------------------------------------------------------------------------------------------------------
    // Tabla de estados e interruptores
    // ------------------------------------------------------------------------------------------------------------------

    public function test_la_tabla_de_estados_del_central_y_su_equivalente_en_la_web(): void
    {
        $this->assertSame(EstadoOrden::Confirmado, EstadoOrden::desdeCentral('confirmado'));
        $this->assertSame(EstadoOrden::Empaquetado, EstadoOrden::desdeCentral('empaquetado'));
        $this->assertSame(EstadoOrden::Despachado, EstadoOrden::desdeCentral('despachado'));
        $this->assertSame(EstadoOrden::Entregado, EstadoOrden::desdeCentral('retirado'));
        $this->assertSame(EstadoOrden::Anulado, EstadoOrden::desdeCentral('anulado'));
        $this->assertSame(EstadoOrden::Cancelado, EstadoOrden::desdeCentral('cancelado'));
        $this->assertNull(EstadoOrden::desdeCentral('reservado'));
        $this->assertNull(EstadoOrden::desdeCentral('requiere_revision'));
        $this->assertNull(EstadoOrden::desdeCentral('algo_nuevo'));
    }

    public function test_el_listener_solo_se_registra_con_la_confirmacion_encendida(): void
    {
        // En el arranque normal (la config de los tests tiene la confirmación apagada) AppServiceProvider no lo registra.
        $this->refreshApplication();

        $this->assertFalse(app('events')->hasListeners(PedidoActualizado::class));
    }

    public function test_con_el_interruptor_encendido_al_arrancar_el_listener_se_registra(): void
    {
        putenv('INTEGRACION_CONFIRMACION=true');
        $_ENV['INTEGRACION_CONFIRMACION'] = 'true';
        $_SERVER['INTEGRACION_CONFIRMACION'] = 'true';

        try {
            $this->refreshApplication();

            $this->assertTrue(app('events')->hasListeners(PedidoActualizado::class));
        } finally {
            putenv('INTEGRACION_CONFIRMACION=false');
            $_ENV['INTEGRACION_CONFIRMACION'] = 'false';
            $_SERVER['INTEGRACION_CONFIRMACION'] = 'false';
            $this->refreshApplication();
        }
    }
}
