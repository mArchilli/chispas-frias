<?php

namespace Tests\Feature\Seguridad;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ErroresYDatosExpuestosTest extends TestCase
{
    use RefreshDatabase;

    public function test_con_debug_apagado_un_error_no_muestra_trazas_ni_mensajes_internos(): void
    {
        config(['app.debug' => false]);

        Route::get('/_prueba-error', fn () => throw new \RuntimeException('clave-interna-secreta en C:/proyecto/app/X.php'));

        $respuesta = $this->get('/_prueba-error');

        $respuesta->assertStatus(500);

        foreach (['clave-interna-secreta', 'RuntimeException', 'Stack trace', 'vendor/laravel', 'vendor\\laravel', '.php:', 'C:/proyecto'] as $filtrado) {
            $this->assertStringNotContainsString($filtrado, $respuesta->getContent());
        }
    }

    public function test_un_404_es_un_404_real_y_no_dice_nada_del_servidor(): void
    {
        $respuesta = $this->get('/no-existe-esta-ruta');

        $respuesta->assertNotFound();
        $this->assertStringNotContainsString('Laravel', (string) $respuesta->headers->get('X-Powered-By'));
    }

    public function test_el_front_solo_recibe_los_campos_del_usuario_que_usa(): void
    {
        $vendedor = User::factory()->vendedor()->create();
        $vendedor->forceFill(['rukoz_vendedor_id' => 'V-77'])->save();

        $this->actingAs($vendedor)->get(route('admin.dashboard'))->assertInertia(fn (Assert $page) => $page
            ->has('auth.user', fn (Assert $user) => $user
                ->where('id', $vendedor->id)
                ->where('role', 'vendedor')
                ->has('name')->has('email')->has('email_verified_at')
                ->missing('password')->missing('remember_token')->missing('rukoz_vendedor_id')->missing('is_active')
            )
        );
    }

    public function test_el_front_no_recibe_usuario_si_no_hay_sesion(): void
    {
        $this->get('/contacto')->assertInertia(fn (Assert $page) => $page->where('auth.user', null));
    }
}
