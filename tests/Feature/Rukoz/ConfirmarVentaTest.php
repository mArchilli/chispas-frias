<?php

namespace Tests\Feature\Rukoz;

use App\Enums\EstadoOrden;
use App\Models\CardPaymentPlan;
use App\Models\DiscountCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\PedidosConCentral;
use Tests\TestCase;

/**
 * 10.4 — «Confirmar venta» desde el admin: `Rukoz::confirmar` contra el Central falso (cliente REAL del paquete, cada body
 * validado contra el contrato 0.1.2 por `ContratoConfirmacionCentral`). Cubre: sin pago, uno y dos pagos, envío, cupón, la
 * retención del despacho, vendedor sin mapeo / sin asignar, doble clic, Central caído, respuesta perdida, rechazo y los
 * interruptores apagados.
 */
class ConfirmarVentaTest extends TestCase
{
    use PedidosConCentral;
    use RefreshDatabase;

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

    // ------------------------------------------------------------------------------------------------------------------
    // El camino feliz
    // ------------------------------------------------------------------------------------------------------------------

    public function test_confirmar_sin_pago_deja_la_venta_con_saldo_y_el_pedido_confirmado(): void
    {
        $pedido = $this->comprar();
        $usuario = $this->vendedor();

        $this->confirmar($pedido, [], $usuario)
            ->assertRedirect(route('admin.orders.show', $pedido))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $pedido->refresh();
        $this->assertSame(EstadoOrden::Confirmado, $pedido->estado);
        $this->assertSame('confirmado', $pedido->rukoz_estado);
        $this->assertNotNull($pedido->confirmado_at);
        $this->assertSame(2, $pedido->rukoz_version);
        $this->assertSame('TST-000001', $pedido->rukoz_numero_venta);
        $this->assertSame($usuario->id, (int) $pedido->confirmado_por);
        // 2 x 1000 + 5000 de envío, sin pagos: queda todo como saldo.
        $this->assertSame(7000.0, (float) $pedido->rukoz_total_venta);
        $this->assertSame(0.0, (float) $pedido->rukoz_pagado);
        $this->assertSame(7000.0, (float) $pedido->rukoz_saldo);
        $this->assertSame(5000.0, (float) $pedido->envio_costo_cobrado);

        // Lo que viajó: vendedor mapeado, sin pagos, el envío, sin `descuento` ni `punto` (esta web tiene un solo punto).
        $body = $this->posts()[0][2];
        $this->assertSame('7', $body['vendedor']['id']);
        $this->assertSame([], $body['pagos']);
        $this->assertSame(['forma_envio_id' => 1, 'costo_cobrado' => 5000, 'es_gratis' => false, 'transportista_id' => 1], $body['envio']);
        $this->assertArrayNotHasKey('descuento', $body);
        $this->assertArrayNotHasKey('punto', $body);
        $this->assertArrayHasKey('confirmado_at', $body);
        $this->assertFalse($body['solo_con_pago_completo']);
    }

    public function test_confirmar_con_un_pago_registra_cuenta_medio_y_monto(): void
    {
        $pedido = $this->comprar();

        $this->confirmar($pedido, ['pagos' => [['cuenta' => 'DIGITAL-CHISPERIO', 'medio' => 'transferencia', 'monto' => 3000]]])
            ->assertSessionHasNoErrors();

        $pedido->refresh();
        $this->assertSame(EstadoOrden::Confirmado, $pedido->estado);
        $this->assertSame(3000.0, (float) $pedido->rukoz_pagado);
        $this->assertSame(4000.0, (float) $pedido->rukoz_saldo);

        $pago = $this->posts()[0][2]['pagos'];
        $this->assertCount(1, $pago);
        $this->assertSame(['cuenta' => 'DIGITAL-CHISPERIO', 'medio' => 'transferencia', 'monto' => 3000], array_diff_key($pago[0], ['fecha' => 1]));
        // La fecha del pago es la misma hora de la confirmación (fija en el primer intento).
        $this->assertSame($this->posts()[0][2]['confirmado_at'], $pago[0]['fecha']);
    }

    public function test_confirmar_con_dos_pagos_los_manda_en_orden_y_suma_lo_pagado(): void
    {
        $pedido = $this->comprar();

        $this->confirmar($pedido, ['pagos' => [
            ['cuenta' => 'DIGITAL-CHISPERIO', 'medio' => 'transferencia', 'monto' => 2000],
            ['cuenta' => 'EFECTIVO', 'medio' => 'efectivo', 'monto' => 5000],
        ]])->assertSessionHasNoErrors();

        $pedido->refresh();
        $this->assertSame(7000.0, (float) $pedido->rukoz_pagado);
        $this->assertSame(0.0, (float) $pedido->rukoz_saldo);
        $this->assertSame(['DIGITAL-CHISPERIO', 'EFECTIVO'], array_column($this->posts()[0][2]['pagos'], 'cuenta'));
    }

    public function test_el_envio_gratis_va_con_costo_cero(): void
    {
        $pedido = $this->comprar(1, 1000);

        $this->confirmar($pedido, ['envio' => $this->envio(['costo_cobrado' => 0, 'es_gratis' => true])])->assertSessionHasNoErrors();

        $this->assertSame(['forma_envio_id' => 1, 'costo_cobrado' => 0, 'es_gratis' => true, 'transportista_id' => 1], $this->posts()[0][2]['envio']);
        $this->assertSame(1000.0, (float) $pedido->fresh()->rukoz_total_venta);
    }

    public function test_un_retiro_sin_costo_ni_transportista_se_confirma(): void
    {
        $pedido = $this->comprar();

        $this->confirmar($pedido, ['envio' => ['forma_envio_id' => 2, 'costo_cobrado' => 0, 'es_gratis' => false]])->assertSessionHasNoErrors();

        $this->assertSame(EstadoOrden::Confirmado, $pedido->fresh()->estado);
        $this->assertArrayNotHasKey('transportista_id', $this->posts()[0][2]['envio']);
    }

    public function test_el_descuento_del_cupon_ya_viaja_en_el_total_y_no_se_manda_otra_vez(): void
    {
        DiscountCode::create(['code' => 'DIEZ', 'percentage' => 10, 'usage_limit' => 5, 'usage_count' => 0, 'is_active' => true]);
        $pedido = $this->comprar(2, 1000, ['cart_discount_code' => 'DIEZ']);
        $this->assertSame(1800.0, (float) $pedido->total);

        $this->confirmar($pedido)->assertSessionHasNoErrors();

        $this->assertArrayNotHasKey('descuento', $this->posts()[0][2]);
        // La venta es: productos con cupón (1800) + envío (5000).
        $this->assertSame(6800.0, (float) $pedido->fresh()->rukoz_total_venta);
    }

    // ------------------------------------------------------------------------------------------------------------------
    // Retención del despacho («Despachar solo con pago completo»)
    // ------------------------------------------------------------------------------------------------------------------

    public function test_con_saldo_la_casilla_de_retencion_viaja_y_el_pedido_queda_retenido(): void
    {
        $pedido = $this->comprar();

        $this->confirmar($pedido, ['solo_con_pago_completo' => true, 'pagos' => [['cuenta' => 'EFECTIVO', 'monto' => 1000]]])->assertSessionHasNoErrors();

        $this->assertTrue($this->posts()[0][2]['solo_con_pago_completo']);
        $pedido->refresh();
        $this->assertTrue($pedido->rukoz_retenido_por_pago);
        $this->assertSame(6000.0, (float) $pedido->rukoz_saldo);
    }

    public function test_sin_saldo_la_retencion_no_se_manda_aunque_la_casilla_venga_tildada(): void
    {
        $pedido = $this->comprar();

        // Los pagos cubren la venta completa (2000 + 5000): no queda nada que esperar.
        $this->confirmar($pedido, ['solo_con_pago_completo' => true, 'pagos' => [['cuenta' => 'EFECTIVO', 'monto' => 7000]]])->assertSessionHasNoErrors();

        $this->assertFalse($this->posts()[0][2]['solo_con_pago_completo']);
        $this->assertFalse((bool) $pedido->fresh()->rukoz_retenido_por_pago);
    }

    public function test_sin_tildar_la_casilla_la_venta_no_queda_retenida(): void
    {
        $pedido = $this->comprar();

        $this->confirmar($pedido)->assertSessionHasNoErrors();

        $this->assertFalse($this->posts()[0][2]['solo_con_pago_completo']);
        $this->assertFalse((bool) $pedido->fresh()->rukoz_retenido_por_pago);
    }

    // ------------------------------------------------------------------------------------------------------------------
    // El vendedor
    // ------------------------------------------------------------------------------------------------------------------

    public function test_un_usuario_sin_vendedor_asignado_no_puede_confirmar_y_el_pedido_no_cambia(): void
    {
        $pedido = $this->comprar();
        $sinMapeo = $this->vendedor(['rukoz_vendedor_id' => null, 'name' => 'Ana Sin Mapeo']);

        $this->confirmar($pedido, [], $sinMapeo)->assertSessionHasErrors('confirmacion');

        $mensaje = session('errors')->first('confirmacion');
        $this->assertStringContainsString('Ana Sin Mapeo', $mensaje);
        $this->assertStringContainsString('vendedor del Sistema Central', $mensaje);
        $this->assertSame([], $this->posts(), 'Ni siquiera se llamó al Central');

        $pedido->refresh();
        $this->assertSame(EstadoOrden::Pendiente, $pedido->estado);
        $this->assertNull($pedido->confirmado_at);
        $this->assertNull($pedido->confirmando_at, 'Sin mapeo no se fija ni la hora del intento');
    }

    public function test_si_el_central_no_tiene_asignado_al_vendedor_se_explica_y_nada_cambia(): void
    {
        $pedido = $this->comprar();
        $this->central->vendedoresAsignados = ['99'];

        $this->confirmar($pedido)->assertSessionHasErrors('confirmacion');

        $this->assertStringContainsString('todavía no está habilitado en el Sistema Central', session('errors')->first('confirmacion'));
        $this->assertSame(EstadoOrden::Pendiente, $pedido->fresh()->estado);
        $this->assertNull($pedido->fresh()->confirmado_at);
    }

    public function test_un_admin_puede_confirmar_a_nombre_de_otro_vendedor_y_un_vendedor_no(): void
    {
        $pedido = $this->comprar();
        $otro = $this->vendedor(['name' => 'Lucas']);
        $admin = $this->admin(['rukoz_vendedor_id' => null]);

        $this->confirmar($pedido, ['vendedor_id' => $otro->id], $admin)->assertSessionHasNoErrors();

        $this->assertSame('7', $this->posts()[0][2]['vendedor']['id']);
        $this->assertSame('Lucas', $this->posts()[0][2]['vendedor']['nombre']);
        $this->assertSame($admin->id, (int) $pedido->fresh()->confirmado_por);

        // Un vendedor (rol) no confirma a nombre de otro.
        $pedido2 = $this->comprar();
        $vendedor = User::factory()->vendedor()->create(['rukoz_vendedor_id' => '8']);
        $this->confirmar($pedido2, ['vendedor_id' => $otro->id], $vendedor)->assertSessionHasErrors('confirmacion');
        $this->assertSame(EstadoOrden::Pendiente, $pedido2->fresh()->estado);
    }

    public function test_el_admin_con_su_propio_mapeo_confirma_como_el_usuario_logueado(): void
    {
        $pedido = $this->comprar();
        $admin = $this->admin(['rukoz_vendedor_id' => '7', 'name' => 'Matias']);

        $this->confirmar($pedido, [], $admin)->assertSessionHasNoErrors();

        $this->assertSame('Matias', $this->posts()[0][2]['vendedor']['nombre']);
    }

    public function test_un_cliente_sin_acceso_al_panel_no_puede_confirmar_ni_ver_los_datos(): void
    {
        $pedido = $this->comprar();
        $cliente = User::factory()->create(['rukoz_vendedor_id' => '7']);

        $this->actingAs($cliente)->post(route('admin.orders.confirm', $pedido), ['pagos' => [], 'envio' => $this->envio()])->assertForbidden();
        $this->actingAs($cliente)->getJson(route('admin.orders.confirmation', $pedido))->assertForbidden();

        $this->assertSame([], $this->posts());
    }

    // ------------------------------------------------------------------------------------------------------------------
    // Idempotencia y fallas
    // ------------------------------------------------------------------------------------------------------------------

    public function test_doble_clic_es_una_sola_venta(): void
    {
        $pedido = $this->comprar();
        $usuario = $this->vendedor();

        $this->confirmar($pedido, [], $usuario)->assertSessionHasNoErrors();
        $this->confirmar($pedido, [], $usuario)->assertSessionHasNoErrors();

        $this->assertCount(1, $this->posts(), 'El segundo clic no llega al Central');
        $this->assertStringContainsString('ya estaba confirmado', session('success'));
        $this->assertSame('TST-000001', $pedido->fresh()->rukoz_numero_venta);
    }

    public function test_dos_clics_simultaneos_se_serializan_con_un_lock_por_pedido(): void
    {
        $pedido = $this->comprar();

        // Otro request ya está confirmando este pedido (tiene el lock).
        $lock = Cache::lock('rukoz:confirmar:'.$pedido->id, 30);
        $this->assertTrue($lock->get());

        $this->confirmar($pedido)->assertSessionHasErrors('confirmacion');
        $this->assertStringContainsString('se está confirmando', session('errors')->first('confirmacion'));
        $this->assertSame([], $this->posts());

        $lock->release();
        $this->confirmar($pedido)->assertSessionHasNoErrors();
        $this->assertCount(1, $this->posts());
    }

    public function test_si_el_central_esta_caido_no_se_confirma_nada_y_el_reintento_lleva_los_mismos_bytes(): void
    {
        $pedido = $this->comprar();
        $usuario = $this->vendedor();
        $datos = ['pagos' => [['cuenta' => 'EFECTIVO', 'medio' => 'efectivo', 'monto' => 1000]]];
        $this->central->caido = true;

        $this->confirmar($pedido, $datos, $usuario)->assertSessionHasErrors('confirmacion');

        $this->assertStringContainsString('No pudimos comunicarnos con el Sistema Central', session('errors')->first('confirmacion'));
        $pedido->refresh();
        $this->assertSame(EstadoOrden::Pendiente, $pedido->estado);
        $this->assertNull($pedido->confirmado_at);
        $this->assertSame('reservado', $pedido->rukoz_estado);
        $this->assertNotNull($pedido->confirmando_at, 'La hora del intento queda fija para el reintento');

        $this->central->caido = false;
        $this->confirmar($pedido, $datos, $usuario)->assertSessionHasNoErrors();

        $this->assertSame(EstadoOrden::Confirmado, $pedido->fresh()->estado);
        $bodies = array_map(fn ($llamada) => json_encode($llamada[2]), $this->posts());
        $this->assertCount(2, $bodies);
        $this->assertSame($bodies[0], $bodies[1], 'Un reintento tiene que ser idéntico (misma hora, mismos pagos)');
    }

    public function test_si_la_respuesta_se_pierde_el_reintento_recupera_la_misma_venta(): void
    {
        $pedido = $this->comprar();
        $this->central->perderRespuestaDeConfirmar = true;

        // El Central procesó la venta pero la web no se enteró.
        $this->confirmar($pedido)->assertSessionHasErrors('confirmacion');
        $this->assertSame(EstadoOrden::Pendiente, $pedido->fresh()->estado);
        $this->assertSame('confirmado', $this->central->pedidos[$pedido->rukoz_ref]['estado']);

        $this->central->perderRespuestaDeConfirmar = false;
        $this->confirmar($pedido)->assertSessionHasNoErrors();

        $this->assertSame(EstadoOrden::Confirmado, $pedido->fresh()->estado);
        $this->assertSame('TST-000001', $pedido->fresh()->rukoz_numero_venta, 'Es la misma venta: no se creó otra');
    }

    public function test_si_el_central_rechaza_se_muestra_su_mensaje_y_el_pedido_no_cambia(): void
    {
        $pedido = $this->comprar();
        $this->central->rechazarConfirmacion = true;

        $this->confirmar($pedido)->assertSessionHasErrors('confirmacion');

        $this->assertStringContainsString('el contenido del pedido cambió', session('errors')->first('confirmacion'));
        $pedido->refresh();
        $this->assertSame(EstadoOrden::Pendiente, $pedido->estado);
        $this->assertNull($pedido->confirmado_at);
        $this->assertNull($pedido->rukoz_numero_venta);
        $this->assertSame('reservado', $pedido->rukoz_estado);
        $this->assertSame(1, $pedido->rukoz_version);
    }

    public function test_si_el_central_ya_lo_tenia_confirmado_con_otros_datos_se_trae_su_estado(): void
    {
        $pedido = $this->comprar();

        // Alguien lo confirmó en el panel del Central, con otro pago, y la web no se enteró.
        $this->central->confirmaciones[$pedido->rukoz_ref] = ['vendedor' => ['id' => '7'], 'pagos' => [], 'envio' => $this->envio(['costo_cobrado' => 123]), 'confirmado_at' => now()->toIso8601String()];
        $this->central->pedidos[$pedido->rukoz_ref]['estado'] = 'confirmado';
        $this->central->pedidos[$pedido->rukoz_ref]['version'] = 2;
        $this->central->pedidos[$pedido->rukoz_ref]['venta'] = ['numero_venta' => 'CEN-000009', 'total' => 2123, 'pagado' => 0, 'saldo' => 2123, 'confirmado_at' => now()->toIso8601String()];

        $this->confirmar($pedido)->assertSessionHasErrors('confirmacion');

        $this->assertStringContainsString('ya estaba confirmado en el Sistema Central', session('errors')->first('confirmacion'));
        $pedido->refresh();
        $this->assertSame(EstadoOrden::Confirmado, $pedido->estado, 'Se reconcilió con el estado real del Central');
        $this->assertSame('CEN-000009', $pedido->rukoz_numero_venta);
    }

    // ------------------------------------------------------------------------------------------------------------------
    // Validaciones locales (antes de llamar al Central)
    // ------------------------------------------------------------------------------------------------------------------

    public function test_los_pagos_no_pueden_superar_el_total_de_la_venta(): void
    {
        $pedido = $this->comprar(); // 2000 + 5000 de envío

        $this->confirmar($pedido, ['pagos' => [['cuenta' => 'EFECTIVO', 'monto' => 7000.5]]])->assertSessionHasErrors('pagos');

        $this->assertSame([], $this->posts());
        $this->assertSame(EstadoOrden::Pendiente, $pedido->fresh()->estado);
    }

    // ------------------------------------------------------------------------------------------------------------------
    // Recargo del medio de pago
    // ------------------------------------------------------------------------------------------------------------------

    public function test_el_recargo_del_medio_de_pago_viaja_aparte_y_no_cambia_el_total_de_la_venta(): void
    {
        $pedido = $this->comprar(); // 2000 + 5000 de envío = 7000

        // Los pagos cubren justo la venta (7000): el recargo (600) lo pagó el cliente de más y no cuenta contra ese tope.
        $this->confirmar($pedido, [
            'pagos' => [['cuenta' => 'DIGITAL-CHISPERIO', 'medio' => 'transferencia', 'monto' => 7000]],
            'recargo_medio_pago' => 600,
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $body = $this->posts()[0][2];
        $this->assertSame(600, $body['recargo_medio_pago']);
        $this->assertSame(7000, $body['pagos'][0]['monto'], 'el monto del pago no incluye el recargo');

        $pedido->refresh();
        $this->assertSame(EstadoOrden::Confirmado, $pedido->estado);
        $this->assertSame(7000.0, (float) $pedido->rukoz_total_venta, 'el recargo no suma al total de la venta');
        $this->assertSame(0.0, (float) $pedido->rukoz_saldo);
    }

    public function test_el_recargo_acepta_centavos_y_el_mensaje_lo_informa(): void
    {
        $pedido = $this->comprar();

        $this->confirmar($pedido, ['recargo_medio_pago' => '123.45'])->assertSessionHasNoErrors();

        $this->assertSame(123.45, $this->posts()[0][2]['recargo_medio_pago']);
        $this->assertStringContainsString('Recargo del medio de pago: $123,45', session('success'));
    }

    public function test_sin_recargo_o_con_recargo_cero_el_body_queda_como_siempre(): void
    {
        foreach ([[], ['recargo_medio_pago' => 0], ['recargo_medio_pago' => '0'], ['recargo_medio_pago' => null]] as $extra) {
            $pedido = $this->comprar();

            $this->confirmar($pedido, $extra)->assertSessionHasNoErrors();

            $posts = $this->posts();
            $this->assertArrayNotHasKey('recargo_medio_pago', end($posts)[2]);
        }
    }

    public function test_un_recargo_negativo_o_que_no_es_un_numero_se_rechaza_antes_de_llamar_al_central(): void
    {
        $pedido = $this->comprar();

        $this->confirmar($pedido, ['recargo_medio_pago' => -10])->assertSessionHasErrors('recargo_medio_pago');
        $this->confirmar($pedido, ['recargo_medio_pago' => 'mucho'])->assertSessionHasErrors('recargo_medio_pago');

        $this->assertSame([], $this->posts());
        $this->assertSame(EstadoOrden::Pendiente, $pedido->fresh()->estado);
    }

    public function test_el_recargo_no_amplia_el_tope_de_los_pagos(): void
    {
        $pedido = $this->comprar(); // 7000 con el envío

        $this->confirmar($pedido, [
            'pagos' => [['cuenta' => 'EFECTIVO', 'monto' => 7500]],
            'recargo_medio_pago' => 600,
        ])->assertSessionHasErrors('pagos');

        $this->assertSame([], $this->posts());
    }

    public function test_el_modal_ofrece_los_planes_de_tarjeta_activos_para_calcular_el_recargo(): void
    {
        $pedido = $this->comprar();
        CardPaymentPlan::factory()->create(['name' => '3 cuotas', 'installments' => 3, 'surcharge_percentage' => 20]);
        CardPaymentPlan::factory()->inactive()->create(['name' => 'apagado']);

        $json = $this->actingAs($this->vendedor())->getJson(route('admin.orders.confirmation', $pedido))->assertOk()->json();

        $this->assertCount(1, $json['planes_pago']);
        $this->assertEquals(['id' => $json['planes_pago'][0]['id'], 'name' => '3 cuotas', 'installments' => 3, 'surcharge_percentage' => 20.0], $json['planes_pago'][0]);
    }

    public function test_cuenta_o_forma_de_envio_inexistente_se_rechaza_antes_de_llamar_al_central(): void
    {
        $pedido = $this->comprar();

        $this->confirmar($pedido, ['pagos' => [['cuenta' => 'NO-EXISTE', 'monto' => 100]]])->assertSessionHasErrors('pagos.0.cuenta');
        $this->confirmar($pedido, ['envio' => $this->envio(['forma_envio_id' => 999])])->assertSessionHasErrors('envio.forma_envio_id');
        $this->confirmar($pedido, ['envio' => ['forma_envio_id' => 2, 'costo_cobrado' => 100]])->assertSessionHasErrors('envio.costo_cobrado'); // un retiro no lleva costo
        $this->confirmar($pedido, ['envio' => $this->envio(['es_gratis' => true])])->assertSessionHasErrors('envio.costo_cobrado'); // gratis con costo
        $this->confirmar($pedido, ['pagos' => [['cuenta' => 'EFECTIVO', 'monto' => 0]]])->assertSessionHasErrors('pagos.0.monto');

        $this->assertSame([], $this->posts());
    }

    // ------------------------------------------------------------------------------------------------------------------
    // Interruptores apagados y pedidos viejos
    // ------------------------------------------------------------------------------------------------------------------

    public function test_con_el_interruptor_apagado_no_se_confirma_y_el_flujo_de_siempre_sigue(): void
    {
        $pedido = $this->comprar();
        config(['integracion.confirmacion' => false]);

        $this->confirmar($pedido)->assertSessionHasErrors('confirmacion');
        $this->assertSame([], $this->posts());
        $this->assertFalse($pedido->fresh()->puedeConfirmarse());

        // Sin la confirmación, el «despachado» manual de la 10.3 sigue funcionando.
        $this->cambiarEstado($pedido, 'despachado')->assertSessionHasNoErrors();
        $this->assertSame(EstadoOrden::Despachado, $pedido->fresh()->estado);
    }

    public function test_un_pedido_viejo_sin_reserva_en_el_central_no_se_confirma_y_sigue_con_su_flujo(): void
    {
        $pedido = $this->comprarSinCentral();
        $this->assertNull($pedido->rukoz_ref);

        $this->confirmar($pedido)->assertSessionHasErrors('confirmacion');
        $this->assertSame([], $this->posts());

        $this->cambiarEstado($pedido, 'despachado')->assertSessionHasNoErrors();
        $this->assertSame(EstadoOrden::Despachado, $pedido->fresh()->estado);
        // Y vuelve a pendiente, como siempre.
        $this->cambiarEstado($pedido, 'pendiente')->assertSessionHasNoErrors();
        $this->assertSame(EstadoOrden::Pendiente, $pedido->fresh()->estado);
    }

    public function test_con_la_confirmacion_encendida_el_despachado_manual_de_un_pedido_del_central_se_reemplaza_por_confirmar(): void
    {
        $pedido = $this->comprar();

        $this->cambiarEstado($pedido, 'despachado')->assertSessionHasErrors('estado');

        $this->assertStringContainsString('Confirmar venta', session('errors')->first('estado'));
        $this->assertSame(EstadoOrden::Pendiente, $pedido->fresh()->estado);
    }

    // ------------------------------------------------------------------------------------------------------------------
    // Datos del modal
    // ------------------------------------------------------------------------------------------------------------------

    public function test_los_datos_del_modal_traen_cuentas_formas_de_envio_resumen_con_notas_y_vendedor(): void
    {
        $pedido = $this->comprar();
        $usuario = $this->vendedor();

        $json = $this->actingAs($usuario)->getJson(route('admin.orders.confirmation', $pedido))->assertOk()->json();

        $this->assertSame('central', $json['referencias']['origen']);
        $this->assertContains('DIGITAL-CHISPERIO', array_column($json['referencias']['cuentas'], 'codigo'));
        $this->assertEquals(2000.0, $json['resumen']['total']);
        $this->assertSame('Chispa', $json['resumen']['items'][0]['titulo']);
        $this->assertSame($usuario->id, $json['vendedor_por_defecto']);
        $this->assertTrue($json['vendedores'][0]['mapeado']);
        $this->assertTrue($json['vendedores'][0]['habilitado_en_central']);
        $this->assertFalse($json['puede_elegir_vendedor']);
        $this->assertArrayNotHasKey('punto', $json);

        // Un admin sí elige, y ve a los usuarios con vendedor asignado.
        $admin = $this->admin(['rukoz_vendedor_id' => '8']);
        $jsonAdmin = $this->actingAs($admin)->getJson(route('admin.orders.confirmation', $pedido))->assertOk()->json();
        $this->assertTrue($jsonAdmin['puede_elegir_vendedor']);
        $this->assertContains($usuario->id, array_column($jsonAdmin['vendedores'], 'id'));
    }

    public function test_el_resumen_del_modal_muestra_la_nota_del_item_y_el_cupon(): void
    {
        DiscountCode::create(['code' => 'DIEZ', 'percentage' => 10, 'usage_limit' => 5, 'usage_count' => 0, 'is_active' => true]);
        $pedido = $this->comprar(2, 1000, ['cart_discount_code' => 'DIEZ']);
        $pedido->items()->update(['custom_color_text' => 'Turquesa']);

        $resumen = $this->actingAs($this->vendedor())->getJson(route('admin.orders.confirmation', $pedido))->assertOk()->json('resumen');

        $this->assertSame('Color: Turquesa', $resumen['items'][0]['nota']);
        $this->assertSame('DIEZ', $resumen['cupon']);
        $this->assertEquals(200.0, $resumen['descuento']);
        $this->assertEquals(1800.0, $resumen['total']);
    }

    public function test_si_el_central_no_responde_el_modal_usa_la_lista_de_respaldo_configurable(): void
    {
        $pedido = $this->comprar();
        $this->central->caido = true;
        config(['integracion.referencias_respaldo' => ['cuentas' => [['codigo' => 'EFECTIVO', 'nombre' => 'Efectivo']], 'formas_envio' => [['id' => 1, 'nombre' => 'A domicilio', 'es_retiro' => false]]]]);

        $json = $this->actingAs($this->vendedor())->getJson(route('admin.orders.confirmation', $pedido))->assertOk()->json();

        $this->assertSame('respaldo', $json['referencias']['origen']);
        $this->assertSame(['EFECTIVO'], array_column($json['referencias']['cuentas'], 'codigo'));
        $this->assertNotNull($json['referencias']['error']);
    }

    public function test_los_datos_del_modal_de_un_pedido_ya_confirmado_se_rechazan(): void
    {
        $pedido = $this->confirmado();

        $this->actingAs($this->vendedor())->getJson(route('admin.orders.confirmation', $pedido))->assertStatus(409);
    }
}
