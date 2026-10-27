<?php

namespace Tests\Feature\Rukoz;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PedidosConCentral;
use Tests\TestCase;

/**
 * Checkout mínimo — completar los datos del cliente después de la compra:
 *  - guardarlos desde la ficha (PATCH admin/orders/{order}/cliente) con el PUT al Central, que reenvía las líneas actuales;
 *  - el bloqueo de «Confirmar venta» para un envío sin DNI, localidad, CP y teléfono, y poder completarlos en el mismo paso;
 *  - un retiro en oficina se confirma sin nada de eso y el modal sabe cómo lo quiso recibir el cliente.
 */
class DatosDelClienteTest extends TestCase
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

    /** Un pedido como el que deja el checkout mínimo: nombre, teléfono y provincia (envío) o nada más (retiro). */
    private function comprarMinimo(array $datos = [], int $cantidad = 2): Order
    {
        $producto = Product::factory()->for(Category::factory())->create(['title' => 'Chispa', 'price' => 1000, 'stock' => 50, 'is_active' => true]);

        $respuesta = $this->withSession(['cart' => [['product_id' => $producto->id, 'quantity' => $cantidad]]])
            ->postJson(route('cart.whatsapp'), ['customer_data' => [
                'tipo_entrega' => 'envio',
                'name' => 'Ana Pérez',
                'phone' => '1155551234',
                'province' => 'buenos-aires',
                ...$datos,
            ]])->assertOk();

        return Order::findOrFail($respuesta->json('order_id'));
    }

    private function comprarRetiro(): Order
    {
        return $this->comprarMinimo(['tipo_entrega' => 'retiro', 'province' => null]);
    }

    private function guardarDatos(Order $pedido, array $datos, $usuario = null)
    {
        return $this->actingAs($usuario ?? $this->vendedor())->patch(route('admin.orders.update-cliente', $pedido), $datos);
    }

    /** @return array<string, string> */
    private function datosCompletos(array $extra = []): array
    {
        return ['dni' => '30111222', 'city' => 'Rosario', 'postal_code' => '2000', 'phone' => '1155551234', ...$extra];
    }

    /** @return list<array{0: string, 1: string, 2: array}> */
    private function puts(): array
    {
        return $this->central->llamadasA('PUT', '#^/pedidos/#');
    }

    private function ultimoPut(): array
    {
        $puts = $this->puts();
        $this->assertNotEmpty($puts, 'No hubo ningún PUT al Central');

        return end($puts)[2];
    }

    // ------------------------------------------------------------------------------------------------------------------
    // Guardar los datos desde la ficha
    // ------------------------------------------------------------------------------------------------------------------

    public function test_guardar_los_datos_hace_el_put_con_las_lineas_actuales_y_no_las_toca(): void
    {
        $pedido = $this->comprarMinimo();
        $lineasAntes = $pedido->items->map->only(['id', 'product_id', 'cantidad', 'precio_unitario', 'subtotal'])->all();
        $totalAntes = (float) $pedido->total;

        $this->guardarDatos($pedido, ['name' => 'Ana Pérez', 'dni' => '30111222', 'city' => 'Rosario', 'postal_code' => '2000', 'phone' => '1155551234'])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Datos del cliente guardados.');

        $pedido->refresh();
        $this->assertSame('30111222', $pedido->dni);
        $this->assertSame('Rosario', $pedido->city);
        $this->assertSame('2000', $pedido->postal_code);
        $this->assertSame(2, $pedido->rukoz_version, 'Un cambio real sube la versión del Central');
        $this->assertEquals($lineasAntes, $pedido->items()->get()->map->only(['id', 'product_id', 'cantidad', 'precio_unitario', 'subtotal'])->all());
        $this->assertEquals($totalAntes, (float) $pedido->total);

        // El PUT lleva TODAS las líneas (el Central reemplaza el pedido completo) y los datos nuevos del cliente.
        $put = $this->ultimoPut();
        $this->assertSame($pedido->rukoz_ref, $put['external_ref']);
        $this->assertCount(1, $put['items']);
        $this->assertSame(2, $put['items'][0]['cantidad']);
        $this->assertEquals($totalAntes, $put['total']);
        $this->assertSame('30111222', $put['cliente']['dni']);
        $this->assertSame('Rosario', $put['cliente']['localidad']);
        $this->assertSame('2000', $put['cliente']['cp']);
    }

    public function test_el_put_conserva_todas_las_lineas_de_un_pedido_con_varias(): void
    {
        $a = Product::factory()->for(Category::factory())->create(['title' => 'A', 'price' => 1000, 'stock' => 50, 'is_active' => true]);
        $b = Product::factory()->for(Category::factory())->create(['title' => 'B', 'price' => 500, 'stock' => 50, 'is_active' => true]);

        $respuesta = $this->withSession(['cart' => [['product_id' => $a->id, 'quantity' => 2], ['product_id' => $b->id, 'quantity' => 3]]])
            ->postJson(route('cart.whatsapp'), ['customer_data' => ['name' => 'Ana Pérez', 'phone' => '1155551234', 'province' => 'cordoba']])->assertOk();
        $pedido = Order::findOrFail($respuesta->json('order_id'));

        $this->guardarDatos($pedido, ['dni' => '30111222'])->assertSessionHasNoErrors();

        $put = $this->ultimoPut();
        $this->assertCount(2, $put['items'], 'El PUT reemplaza el pedido: las líneas que no viajan se borran en el Central');
        $this->assertSame([2, 3], array_column($put['items'], 'cantidad'));
        $this->assertCount(2, $pedido->fresh()->items);
    }

    public function test_guardar_los_mismos_datos_no_hace_put_ni_sube_la_version(): void
    {
        $pedido = $this->comprarMinimo();

        $this->guardarDatos($pedido, ['name' => 'Ana Pérez', 'phone' => '1155551234', 'province' => 'buenos-aires'])->assertSessionHasNoErrors();

        $this->assertSame([], $this->puts());
        $this->assertSame(1, $pedido->fresh()->rukoz_version);
    }

    public function test_el_nombre_y_apellido_se_edita_como_un_solo_campo_y_junta_un_apellido_viejo(): void
    {
        $pedido = $this->comprarMinimo(['name' => 'Juana', 'lastname' => 'Gómez']);
        $this->assertSame('Gómez', $pedido->lastname);

        $this->guardarDatos($pedido, ['name' => 'Juana María Gómez'])->assertSessionHasNoErrors();

        $pedido->refresh();
        $this->assertSame('Juana María Gómez', $pedido->name);
        $this->assertNull($pedido->lastname);
        $this->assertSame('Juana María Gómez', $this->ultimoPut()['cliente']['nombre']);
    }

    public function test_la_provincia_se_corrige_con_una_de_las_24_y_viaja_con_su_nombre(): void
    {
        $pedido = $this->comprarMinimo();

        $this->guardarDatos($pedido, ['province' => 'tierra-del-fuego'])->assertSessionHasNoErrors();

        $this->assertSame('tierra-del-fuego', $pedido->fresh()->province);
        $this->assertSame('Tierra del Fuego', $this->ultimoPut()['cliente']['provincia']);

        $this->guardarDatos($pedido, ['province' => 'atlantida'])->assertSessionHasErrors('province');
    }

    public function test_un_pedido_de_la_web_sola_se_guarda_sin_llamar_al_central(): void
    {
        config(['integracion.checkout' => false]);
        $pedido = $this->comprarMinimo();
        config(['integracion.checkout' => true]);
        $this->assertNull($pedido->rukoz_ref);

        $this->guardarDatos($pedido, ['dni' => '30111222'])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame('30111222', $pedido->fresh()->dni);
        $this->assertSame([], $this->puts());
    }

    public function test_un_pedido_confirmado_no_acepta_cambios_de_datos(): void
    {
        $pedido = $this->comprarMinimo();
        $this->confirmar($pedido, ['cliente' => $this->datosCompletos()])->assertSessionHasNoErrors();

        $this->guardarDatos($pedido, ['dni' => '1'])->assertSessionHasErrors('estado');

        $this->assertSame('30111222', $pedido->fresh()->dni);
    }

    public function test_un_pedido_cancelado_no_acepta_cambios_de_datos(): void
    {
        $pedido = $this->comprarMinimo();
        $this->cambiarEstado($pedido, 'cancelado')->assertSessionHasNoErrors();

        $this->guardarDatos($pedido, ['dni' => '1'])->assertSessionHasErrors('estado');

        $this->assertNull($pedido->fresh()->dni);
    }

    public function test_si_el_central_no_responde_no_se_guarda_nada(): void
    {
        $pedido = $this->comprarMinimo();
        $this->central->caido = true;

        $this->guardarDatos($pedido, ['dni' => '30111222'])->assertSessionHasErrors('estado');

        $this->assertNull($pedido->fresh()->dni);
        $this->assertSame(1, $pedido->fresh()->rukoz_version);
    }

    public function test_si_el_central_rechaza_el_put_no_se_guarda_nada(): void
    {
        $pedido = $this->comprarMinimo();
        $this->central->errorForzado = [422, ['error' => ['codigo' => 'VALIDACION', 'mensaje' => 'Datos inválidos.', 'detalle' => ['errores' => ['x']]]]];

        $this->guardarDatos($pedido, ['dni' => '30111222'])->assertSessionHasErrors('estado');

        $this->assertNull($pedido->fresh()->dni);
    }

    public function test_el_telefono_y_el_nombre_que_se_guardan_tienen_que_ser_validos(): void
    {
        $pedido = $this->comprarMinimo();

        $this->guardarDatos($pedido, ['phone' => '123'])->assertSessionHasErrors('phone');
        $this->guardarDatos($pedido, ['name' => ''])->assertSessionHasErrors('name');
        $this->guardarDatos($pedido, ['email' => 'no-es-un-email'])->assertSessionHasErrors('email');

        $this->assertSame([], $this->puts());
    }

    public function test_un_cliente_sin_acceso_al_panel_no_puede_guardar_datos(): void
    {
        $pedido = $this->comprarMinimo();

        $this->actingAs(\App\Models\User::factory()->create())
            ->patch(route('admin.orders.update-cliente', $pedido), ['dni' => '1'])
            ->assertForbidden();

        $this->assertNull($pedido->fresh()->dni);
    }

    // ------------------------------------------------------------------------------------------------------------------
    // La ficha del pedido
    // ------------------------------------------------------------------------------------------------------------------

    public function test_la_ficha_expone_el_tipo_de_entrega_el_nombre_completo_y_si_se_puede_editar(): void
    {
        $envio = $this->comprarMinimo(['name' => 'Ana', 'lastname' => 'Pérez']);
        $retiro = $this->comprarRetiro();

        $this->actingAs($this->vendedor())->get(route('admin.orders.show', $envio))->assertInertia(fn ($page) => $page
            ->where('order.tipo_entrega', 'envio')
            ->where('order.tipo_entrega_label', 'Envío')
            ->where('order.nombre_completo', 'Ana Pérez')
            ->where('order.datos_cliente_solo_lectura', null)
            ->where('order.datos_requeridos.postal_code', 'código postal')
            ->has('provinces', 24)
        );

        $this->actingAs($this->vendedor())->get(route('admin.orders.show', $retiro))->assertInertia(fn ($page) => $page
            ->where('order.tipo_entrega', 'retiro')
            ->where('order.tipo_entrega_label', 'Retiro en oficina · Buenos Aires')
            ->where('order.province', null)
        );

        $this->confirmar($envio, ['cliente' => $this->datosCompletos()])->assertSessionHasNoErrors();
        $this->actingAs($this->vendedor())->get(route('admin.orders.show', $envio))->assertInertia(fn ($page) => $page
            ->where('order.datos_cliente_solo_lectura', fn ($motivo) => str_contains((string) $motivo, 'solo lectura'))
        );
    }

    public function test_el_listado_busca_por_telefono_y_muestra_la_oficina_de_un_retiro(): void
    {
        $this->comprarMinimo(['phone' => '11 7777-0000']);
        $retiro = $this->comprarRetiro();
        Order::whereKey($retiro->id)->update(['created_at' => now()->addMinute()]); // el más nuevo, primero en el listado

        $this->actingAs($this->vendedor())->get(route('admin.orders.index', ['search' => '7777']))->assertInertia(fn ($page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.nombre_completo', 'Ana Pérez')
            ->where('orders.data.0.province', 'Buenos Aires')
        );

        $this->actingAs($this->vendedor())->get(route('admin.orders.index'))->assertInertia(fn ($page) => $page
            ->has('orders.data', 2)
            ->where('orders.data.0.province', 'Retiro en oficina · Buenos Aires')
        );
    }

    // ------------------------------------------------------------------------------------------------------------------
    // Confirmar venta
    // ------------------------------------------------------------------------------------------------------------------

    public function test_un_envio_sin_datos_no_se_confirma_y_dice_que_falta(): void
    {
        $pedido = $this->comprarMinimo();

        $this->confirmar($pedido)->assertSessionHasErrors(['confirmacion', 'cliente.dni', 'cliente.city', 'cliente.postal_code']);

        $this->assertStringContainsString('DNI, localidad, código postal', session('errors')->first('confirmacion'));
        $this->assertSame([], $this->posts(), 'No se llamó al Central');
        $this->assertNull($pedido->fresh()->confirmado_at);
    }

    public function test_los_datos_completados_en_el_modal_se_guardan_con_el_put_antes_de_confirmar(): void
    {
        $pedido = $this->comprarMinimo();

        $this->confirmar($pedido, ['cliente' => $this->datosCompletos()])
            ->assertRedirect(route('admin.orders.show', $pedido))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $pedido->refresh();
        $this->assertSame('30111222', $pedido->dni);
        $this->assertSame('Rosario', $pedido->city);
        $this->assertNotNull($pedido->confirmado_at);
        $this->assertCount(1, $this->posts());

        // Orden: primero el PUT con los datos, después el confirmar.
        $put = $this->ultimoPut();
        $this->assertSame('30111222', $put['cliente']['dni']);
        $this->assertSame('2000', $put['cliente']['cp']);
        $metodos = array_map(fn ($llamada) => $llamada[0].' '.preg_replace('#/pedidos/[^/]+#', '/pedidos/ref', $llamada[1]), $this->central->llamadas);
        $this->assertLessThan(array_search('POST /pedidos/ref/confirmar', $metodos, true), array_search('PUT /pedidos/ref', $metodos, true));
    }

    public function test_un_envio_con_los_datos_ya_cargados_se_confirma_sin_otro_put(): void
    {
        $pedido = $this->comprarMinimo($this->datosCompletos(['dni' => '30111222']));
        $putsAntes = count($this->puts());

        $this->confirmar($pedido)->assertSessionHasNoErrors();

        $this->assertNotNull($pedido->fresh()->confirmado_at);
        $this->assertCount($putsAntes, $this->puts(), 'No había nada que guardar');
    }

    public function test_falta_un_solo_dato_y_no_se_confirma(): void
    {
        $pedido = $this->comprarMinimo();

        $this->confirmar($pedido, ['cliente' => $this->datosCompletos(['postal_code' => ''])])
            ->assertSessionHasErrors(['confirmacion', 'cliente.postal_code']);

        $this->assertSame([], $this->posts());
        $this->assertNull($pedido->fresh()->dni, 'Si no se puede confirmar, tampoco se guardó nada');
    }

    public function test_si_el_put_de_los_datos_falla_no_se_confirma(): void
    {
        $pedido = $this->comprarMinimo();
        $this->central->errorForzado = [422, ['error' => ['codigo' => 'VALIDACION', 'mensaje' => 'Datos inválidos.', 'detalle' => ['errores' => ['x']]]]];

        $this->confirmar($pedido, ['cliente' => $this->datosCompletos()])->assertSessionHasErrors('confirmacion');

        $this->assertStringContainsString('no se confirmó la venta', session('errors')->first('confirmacion'));
        $this->assertSame([], $this->posts());
        $this->assertNull($pedido->fresh()->dni);
    }

    public function test_un_retiro_en_oficina_se_confirma_sin_ningun_dato_de_envio(): void
    {
        $pedido = $this->comprarRetiro();
        $this->assertNull($pedido->dni);

        $this->confirmar($pedido, ['envio' => $this->envio(['forma_envio_id' => 2, 'costo_cobrado' => 0, 'transportista_id' => null])])
            ->assertRedirect(route('admin.orders.show', $pedido))
            ->assertSessionHasNoErrors();

        $this->assertNotNull($pedido->fresh()->confirmado_at);
        $this->assertCount(1, $this->posts());
    }

    public function test_el_bloqueo_se_decide_por_la_forma_de_envio_elegida_en_el_servidor(): void
    {
        // Un pedido que el cliente quiso retirar pero el vendedor despacha con transportista: ahora sí hacen falta los datos.
        $pedido = $this->comprarRetiro();

        $this->confirmar($pedido)->assertSessionHasErrors(['confirmacion', 'cliente.dni']);

        $this->assertSame([], $this->posts());
    }

    public function test_el_modal_recibe_el_tipo_de_entrega_y_los_datos_actuales(): void
    {
        $envio = $this->comprarMinimo(['dni' => '30111222']);
        $retiro = $this->comprarRetiro();
        $usuario = $this->vendedor();

        $this->actingAs($usuario)->getJson(route('admin.orders.confirmation', $envio))
            ->assertOk()
            ->assertJsonPath('tipo_entrega', 'envio')
            ->assertJsonPath('cliente.dni', '30111222')
            ->assertJsonPath('cliente.city', null)
            ->assertJsonPath('cliente.phone', '1155551234')
            ->assertJsonPath('datos_requeridos.postal_code', 'código postal');

        $this->actingAs($usuario)->getJson(route('admin.orders.confirmation', $retiro))
            ->assertOk()
            ->assertJsonPath('tipo_entrega', 'retiro');
    }
}
