<?php

namespace Tests\Feature\Rukoz;

use App\Models\User;
use App\Rukoz\ReferenciasDelCentral;
use App\Rukoz\VendedorParaCentral;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Tests\Support\CentralDePrueba;
use Tests\Support\ContratoConfirmacionCentral;
use Tests\TestCase;

/**
 * 10.4 — El mapeo usuario de la web → vendedor del Central (`users.rukoz_vendedor_id`): por comando y desde Admin → Vendedores, el
 * adaptador `VendedorParaCentral` y el validador de contrato de `confirmar`.
 */
class VendedorMapeoTest extends TestCase
{
    use RefreshDatabase;

    private CentralDePrueba $central;

    protected function setUp(): void
    {
        parent::setUp();

        $this->central = CentralDePrueba::instalar();
        config(['integracion.confirmacion' => true]);
    }

    public function test_el_adaptador_arma_el_vendedor_con_el_id_mapeado_y_se_niega_sin_mapeo(): void
    {
        $ana = User::factory()->vendedor()->create(['name' => 'Ana', 'email' => 'ana@chispasfrias.test', 'rukoz_vendedor_id' => ' 7 ']);
        $this->assertSame(['id' => '7', 'email' => 'ana@chispasfrias.test', 'nombre' => 'Ana'], VendedorParaCentral::payload($ana));

        $this->expectException(ValidationException::class);
        VendedorParaCentral::payload(User::factory()->vendedor()->create(['rukoz_vendedor_id' => null]));
    }

    public function test_el_comando_mapea_quita_y_no_permite_repetir_un_vendedor(): void
    {
        $ana = User::factory()->vendedor()->create(['email' => 'ana@chispasfrias.test']);
        $luis = User::factory()->vendedor()->create(['email' => 'luis@chispasfrias.test']);

        $this->assertSame(0, Artisan::call('chispasfrias:vendedor-mapear', ['usuario' => 'ana@chispasfrias.test', 'vendedor' => '7']));
        $this->assertSame('7', $ana->fresh()->rukoz_vendedor_id);
        $this->assertStringContainsString('puede confirmar ventas', Artisan::output());

        // Otro usuario no puede quedarse con el mismo vendedor.
        $this->assertSame(1, Artisan::call('chispasfrias:vendedor-mapear', ['usuario' => 'luis@chispasfrias.test', 'vendedor' => '7']));
        $this->assertNull($luis->fresh()->rukoz_vendedor_id);

        // Sin el argumento `vendedor` usa el id del usuario.
        $this->assertSame(0, Artisan::call('chispasfrias:vendedor-mapear', ['usuario' => (string) $luis->id]));
        $this->assertSame((string) $luis->id, $luis->fresh()->rukoz_vendedor_id);

        $this->assertSame(0, Artisan::call('chispasfrias:vendedor-mapear', ['usuario' => 'ana@chispasfrias.test', '--quitar' => true]));
        $this->assertNull($ana->fresh()->rukoz_vendedor_id);

        $this->assertSame(1, Artisan::call('chispasfrias:vendedor-mapear', ['usuario' => 'nadie@chispasfrias.test', 'vendedor' => '1']));
    }

    public function test_el_comando_avisa_si_el_central_todavia_no_lo_tiene_asignado_y_lista(): void
    {
        User::factory()->vendedor()->create(['email' => 'ana@chispasfrias.test']);

        Artisan::call('chispasfrias:vendedor-mapear', ['usuario' => 'ana@chispasfrias.test', 'vendedor' => '55']);
        $this->assertStringContainsString('todavía no lo tiene asignado', Artisan::output());

        Artisan::call('chispasfrias:vendedor-mapear', ['--listar' => true]);
        $salida = Artisan::output();
        $this->assertStringContainsString('ana@chispasfrias.test', $salida);
        $this->assertStringContainsString('NO', $salida);
        $this->assertStringContainsString('Vendedor 7', $salida); // el que el Central ya tiene asignado
    }

    public function test_el_admin_edita_el_mapeo_desde_vendedores_con_la_confirmacion_encendida(): void
    {
        $admin = User::factory()->admin()->create();
        $ana = User::factory()->vendedor()->create(['rukoz_vendedor_id' => null]);

        $this->actingAs($admin)->get(route('admin.sellers.edit', $ana))
            ->assertInertia(fn ($page) => $page
                ->where('integracion.confirmacion', true)
                ->where('seller.rukoz_vendedor_id', null)
                ->has('integracion.vendedores_del_central', 1)
            );

        $this->actingAs($admin)->put(route('admin.sellers.update', $ana), ['name' => $ana->name, 'email' => $ana->email, 'rukoz_vendedor_id' => '7'])
            ->assertSessionHasNoErrors();
        $this->assertSame('7', $ana->fresh()->rukoz_vendedor_id);

        // Repetido en otro usuario = error.
        $otro = User::factory()->vendedor()->create(['rukoz_vendedor_id' => null]);
        $this->actingAs($admin)->put(route('admin.sellers.update', $otro), ['name' => $otro->name, 'email' => $otro->email, 'rukoz_vendedor_id' => '7'])
            ->assertSessionHasErrors('rukoz_vendedor_id');
        $this->assertNull($otro->fresh()->rukoz_vendedor_id);

        // Vacío = sin mapeo.
        $this->actingAs($admin)->put(route('admin.sellers.update', $ana), ['name' => $ana->name, 'email' => $ana->email, 'rukoz_vendedor_id' => ''])
            ->assertSessionHasNoErrors();
        $this->assertNull($ana->fresh()->rukoz_vendedor_id);
    }

    public function test_con_la_confirmacion_apagada_el_formulario_no_ofrece_el_campo(): void
    {
        config(['integracion.confirmacion' => false]);
        $ana = User::factory()->vendedor()->create(['rukoz_vendedor_id' => '7']);

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.sellers.edit', $ana))
            ->assertInertia(fn ($page) => $page->where('integracion.confirmacion', false)->where('integracion.vendedores_del_central', []));
    }

    public function test_si_el_formulario_no_manda_el_campo_el_mapeo_existente_no_se_toca(): void
    {
        $ana = User::factory()->vendedor()->create(['rukoz_vendedor_id' => '7']);

        $this->actingAs(User::factory()->admin()->create())->put(route('admin.sellers.update', $ana), ['name' => 'Ana Nueva', 'email' => $ana->email])
            ->assertSessionHasNoErrors();

        $this->assertSame('7', $ana->fresh()->rukoz_vendedor_id);
        $this->assertSame('Ana Nueva', $ana->fresh()->name);
    }

    public function test_un_vendedor_no_puede_editar_el_mapeo_de_nadie(): void
    {
        $ana = User::factory()->vendedor()->create(['rukoz_vendedor_id' => null]);

        $this->actingAs(User::factory()->vendedor()->create())->put(route('admin.sellers.update', $ana), ['name' => $ana->name, 'email' => $ana->email, 'rukoz_vendedor_id' => '9'])
            ->assertForbidden();

        $this->assertNull($ana->fresh()->rukoz_vendedor_id);
    }

    public function test_las_referencias_se_cachean_y_se_pueden_olvidar(): void
    {
        $this->assertSame('central', ReferenciasDelCentral::obtener()['origen']);
        $antes = count($this->central->llamadasA('GET', '#^/referencias#'));

        ReferenciasDelCentral::obtener();
        ReferenciasDelCentral::obtener();
        $this->assertCount($antes, $this->central->llamadasA('GET', '#^/referencias#'), 'La segunda y tercera lectura salen del caché');

        ReferenciasDelCentral::olvidar();
        ReferenciasDelCentral::obtener();
        $this->assertGreaterThan($antes, count($this->central->llamadasA('GET', '#^/referencias#')));
    }

    // ------------------------------------------------------------------------------------------------------------------
    // El validador de contrato de `confirmar`
    // ------------------------------------------------------------------------------------------------------------------

    public function test_el_validador_acepta_el_ejemplo_oficial_y_rechaza_lo_que_el_central_rechazaria(): void
    {
        $oficial = json_decode((string) file_get_contents(base_path('tests/fixtures/rukoz/request-pedido-confirmar.json')), true);
        $referencias = json_decode((string) file_get_contents(base_path('tests/fixtures/rukoz/response-referencias.json')), true);

        // El ejemplo oficial trae `descuento: 0`: el Central lo admite, pero esta web no lo manda NUNCA.
        $this->assertSame([], array_values(array_filter(ContratoConfirmacionCentral::errores($oficial, $referencias, 53500.0), fn ($e) => ! str_starts_with($e, 'descuento'))));

        $sinDescuento = array_diff_key($oficial, ['descuento' => 1]);
        $this->assertSame([], ContratoConfirmacionCentral::errores($sinDescuento, $referencias, 53500.0));

        $this->assertNotSame([], ContratoConfirmacionCentral::errores($oficial, $referencias, 53500.0), 'con `descuento` falla');
        $this->assertNotSame([], ContratoConfirmacionCentral::errores([...$sinDescuento, 'campo_inventado' => 1]));
        $this->assertNotSame([], ContratoConfirmacionCentral::errores([...$sinDescuento, 'pagos' => [['cuenta' => 'EFECTIVO', 'monto' => 0]]]));
        $this->assertNotSame([], ContratoConfirmacionCentral::errores([...$sinDescuento, 'pagos' => [['cuenta' => 'NO-EXISTE', 'monto' => 10]]], $referencias));
        $this->assertNotSame([], ContratoConfirmacionCentral::errores([...$sinDescuento, 'pagos' => [['cuenta' => 'EFECTIVO', 'monto' => 99999]]], $referencias, 1000.0), 'pagos de más');
        $this->assertNotSame([], ContratoConfirmacionCentral::errores([...$sinDescuento, 'envio' => ['forma_envio_id' => 2, 'costo_cobrado' => 10]], $referencias), 'un retiro con costo');
        $this->assertNotSame([], ContratoConfirmacionCentral::errores([...$sinDescuento, 'vendedor' => ['email' => 'x@y.z']]));
    }
}
