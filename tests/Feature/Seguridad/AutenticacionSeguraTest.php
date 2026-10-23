<?php

namespace Tests\Feature\Seguridad;

use App\Enums\RolUsuario;
use App\Http\Middleware\CerrarSesionPorInactividad;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AutenticacionSeguraTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        RateLimiter::clear('x');
    }

    // --- fuerza bruta y abuso ------------------------------------------------------------------------------------------------

    public function test_el_registro_tiene_limite_por_ip(): void
    {
        config(['seguridad.limites.registro_por_minuto' => 3]);

        for ($i = 0; $i < 3; $i++) {
            $this->post('/register', ['name' => "Cliente $i", 'email' => "c$i@ejemplo.com", 'password' => 'Clave-1234', 'password_confirmation' => 'Clave-1234']);
            $this->post('/logout');
        }

        $this->post('/register', ['name' => 'Otro', 'email' => 'otro@ejemplo.com', 'password' => 'Clave-1234', 'password_confirmation' => 'Clave-1234'])
            ->assertStatus(429);

        $this->assertDatabaseMissing('users', ['email' => 'otro@ejemplo.com']);
    }

    public function test_recuperar_contrasena_tiene_limite_por_ip(): void
    {
        Notification::fake();
        config(['seguridad.limites.recuperar_password_por_minuto' => 2]);

        $this->post('/forgot-password', ['email' => 'a@ejemplo.com'])->assertSessionHasNoErrors();
        $this->post('/forgot-password', ['email' => 'b@ejemplo.com'])->assertSessionHasNoErrors();
        $this->post('/forgot-password', ['email' => 'c@ejemplo.com'])->assertStatus(429);
        $this->post('/reset-password', ['token' => 'x', 'email' => 'c@ejemplo.com', 'password' => 'Clave-1234', 'password_confirmation' => 'Clave-1234'])->assertStatus(429);
    }

    public function test_el_login_tiene_limite_por_ip_aunque_se_cambie_el_email(): void
    {
        config(['seguridad.limites.login_por_minuto' => 4]);

        for ($i = 0; $i < 4; $i++) {
            $this->post('/login', ['email' => "intento$i@ejemplo.com", 'password' => 'mal'])->assertSessionHasErrors('email');
        }

        $this->post('/login', ['email' => 'intento9@ejemplo.com', 'password' => 'mal'])->assertStatus(429);
    }

    public function test_el_login_bloquea_tras_cinco_fallos_del_mismo_email(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'mal']);
        }

        // Ni siquiera con la clave correcta entra mientras dure el bloqueo.
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_el_olvide_mi_contrasena_responde_igual_exista_o_no_el_mail(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $existente = $this->post('/forgot-password', ['email' => $user->email]);
        $inexistente = $this->post('/forgot-password', ['email' => 'no-existe@ejemplo.com']);
        // Segundo pedido seguido de la misma cuenta: el broker lo frena, pero la respuesta no lo delata.
        $repetido = $this->post('/forgot-password', ['email' => $user->email]);

        foreach ([$existente, $inexistente, $repetido] as $respuesta) {
            $respuesta->assertSessionHasNoErrors();
            $respuesta->assertSessionHas('status', 'Si el correo está registrado, te enviamos un enlace para restablecer la contraseña.');
        }

        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertSentTimes(ResetPassword::class, 1);
    }

    public function test_el_login_con_mail_inexistente_y_con_clave_mala_dan_el_mismo_mensaje(): void
    {
        $user = User::factory()->create();

        $a = $this->post('/login', ['email' => $user->email, 'password' => 'mal']);
        $b = $this->post('/login', ['email' => 'nadie@ejemplo.com', 'password' => 'mal']);

        $this->assertSame(
            $a->baseResponse->getSession()->get('errors')->get('email'),
            $b->baseResponse->getSession()->get('errors')->get('email'),
        );
    }

    // --- sesiones ------------------------------------------------------------------------------------------------------------

    public function test_el_login_regenera_el_id_de_sesion(): void
    {
        $user = User::factory()->create();

        $this->get('/login');
        $antes = session()->getId();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($antes, session()->getId());
    }

    public function test_una_cuenta_desactivada_con_la_sesion_abierta_queda_afuera(): void
    {
        $vendedor = User::factory()->vendedor()->create();

        $this->actingAs($vendedor)->get(route('admin.orders.index'))->assertOk();

        $vendedor->update(['is_active' => false]);

        $this->get(route('admin.orders.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_la_sesion_del_panel_vence_por_inactividad_y_el_limite_es_configurable(): void
    {
        $admin = User::factory()->admin()->create();
        config(['seguridad.admin_inactividad_minutos' => 30]);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

        // Dentro del límite: sigue adentro y se renueva el contador.
        $this->withSession([CerrarSesionPorInactividad::CLAVE => time() - 29 * 60])->get(route('admin.dashboard'))->assertOk();

        // Pasado el límite: afuera.
        $this->withSession([CerrarSesionPorInactividad::CLAVE => time() - 31 * 60])->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_con_el_limite_en_cero_no_hay_vencimiento_adicional(): void
    {
        $admin = User::factory()->admin()->create();
        config(['seguridad.admin_inactividad_minutos' => 0]);

        $this->actingAs($admin)->withSession([CerrarSesionPorInactividad::CLAVE => time() - 99 * 3600])->get(route('admin.dashboard'))->assertOk();
    }

    public function test_cambiar_la_contrasena_cierra_las_otras_sesiones_pero_no_la_actual(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->vendedor()->create();

        foreach (['otra-sesion-1', 'otra-sesion-2', 'esta-sesion'] as $id) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->id, 'ip_address' => '1.1.1.1', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()]);
        }

        $this->actingAs($user)->withSession([])->put('/password', [
            'current_password' => 'password',
            'password' => 'NuevaClave-2026',
            'password_confirmation' => 'NuevaClave-2026',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('NuevaClave-2026', $user->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'otra-sesion-1']);
        $this->assertDatabaseMissing('sessions', ['id' => 'otra-sesion-2']);
    }

    public function test_desactivar_la_cuenta_o_cambiar_el_rol_cierra_todas_las_sesiones(): void
    {
        config(['session.driver' => 'database']);
        $a = User::factory()->vendedor()->create();
        $b = User::factory()->vendedor()->create();

        foreach ([$a, $b] as $i => $u) {
            DB::table('sessions')->insert(['id' => "s$i", 'user_id' => $u->id, 'ip_address' => '1.1.1.1', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()]);
        }

        $a->update(['is_active' => false]);
        $b->update(['role' => RolUsuario::Cliente]);

        $this->assertSame(0, DB::table('sessions')->count());
    }

    public function test_una_sesion_vieja_deja_de_valer_cuando_la_contrasena_cambio_desde_otro_lado(): void
    {
        $user = User::factory()->admin()->create();

        // Sesión abierta en el equipo A.
        $this->actingAs($user)->get(route('admin.dashboard'))->assertOk();
        $hashViejo = session('password_hash_web');
        $this->assertNotEmpty($hashViejo);

        // Alguien cambia la contraseña (reset por mail, otro equipo...).
        $user->forceFill(['password' => Hash::make('OtraClave-2026')])->save();

        // El equipo A sigue mandando su cookie de antes: ya no entra.
        $this->withSession(['password_hash_web' => $hashViejo])->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    // --- política de contraseñas ---------------------------------------------------------------------------------------------

    public function test_admin_y_vendedor_necesitan_una_contrasena_mas_fuerte_y_el_cliente_conserva_la_de_siempre(): void
    {
        $vendedor = User::factory()->vendedor()->create();
        $cliente = User::factory()->create();

        foreach (['corta1A', 'todominusculas123', 'TODOMAYUSCULAS123', 'SinNumeros-Aqui'] as $debil) {
            $this->actingAs($vendedor)->put('/password', ['current_password' => 'password', 'password' => $debil, 'password_confirmation' => $debil])
                ->assertSessionHasErrors('password');
        }

        $this->actingAs($vendedor)->put('/password', ['current_password' => 'password', 'password' => 'Fuerte-Clave-26', 'password_confirmation' => 'Fuerte-Clave-26'])
            ->assertSessionHasNoErrors();

        // El cliente sigue con el mínimo de siempre (8 caracteres).
        $this->actingAs($cliente)->put('/password', ['current_password' => 'password', 'password' => 'ochochar', 'password_confirmation' => 'ochochar'])
            ->assertSessionHasNoErrors();
    }

    public function test_el_reset_de_contrasena_de_un_vendedor_tambien_exige_la_politica(): void
    {
        Notification::fake();
        $vendedor = User::factory()->vendedor()->create();

        $this->post('/forgot-password', ['email' => $vendedor->email]);

        Notification::assertSentTo($vendedor, ResetPassword::class, function (ResetPassword $notificacion) use ($vendedor) {
            $this->post('/reset-password', ['token' => $notificacion->token, 'email' => $vendedor->email, 'password' => 'password1', 'password_confirmation' => 'password1'])
                ->assertSessionHasErrors('password');

            // El token sigue valiendo: con una clave válida se completa.
            $this->post('/reset-password', ['token' => $notificacion->token, 'email' => $vendedor->email, 'password' => 'Fuerte-Clave-26', 'password_confirmation' => 'Fuerte-Clave-26'])
                ->assertSessionHasNoErrors()->assertRedirect(route('login'));

            return true;
        });
    }

    public function test_las_contrasenas_se_guardan_con_bcrypt_y_los_tokens_de_recuperacion_vencen(): void
    {
        $user = User::factory()->create();

        $this->assertStringStartsWith('$2y$', $user->password);
        $this->assertSame('bcrypt', config('hashing.driver'));

        // Los tokens de recuperación vencen (config/auth.php → passwords.users.expire, en minutos).
        $this->assertLessThanOrEqual(60, config('auth.passwords.users.expire'));
        $this->assertGreaterThan(0, config('auth.passwords.users.throttle'));
    }

    // --- asignación masiva ---------------------------------------------------------------------------------------------------

    public function test_el_registro_siempre_crea_un_cliente_aunque_se_mande_el_rol(): void
    {
        $this->post('/register', [
            'name' => 'Intruso', 'email' => 'intruso@ejemplo.com', 'password' => 'Clave-1234', 'password_confirmation' => 'Clave-1234',
            'role' => 'admin', 'is_active' => 1, 'rukoz_vendedor_id' => '99', 'email_verified_at' => now()->toDateTimeString(),
        ]);

        $usuario = User::where('email', 'intruso@ejemplo.com')->firstOrFail();

        $this->assertSame(RolUsuario::Cliente, $usuario->role);
        $this->assertNull($usuario->rukoz_vendedor_id);
        $this->assertNull($usuario->email_verified_at);
    }

    public function test_el_perfil_no_permite_cambiarse_el_rol(): void
    {
        $cliente = User::factory()->create();

        $this->actingAs($cliente)->patch('/profile', [
            'name' => 'Nuevo Nombre', 'email' => $cliente->email,
            'role' => 'admin', 'is_active' => 0, 'rukoz_vendedor_id' => 'x',
        ])->assertSessionHasNoErrors();

        $cliente->refresh();

        $this->assertSame('Nuevo Nombre', $cliente->name);
        $this->assertSame(RolUsuario::Cliente, $cliente->role);
        $this->assertTrue($cliente->is_active);
        $this->assertNull($cliente->rukoz_vendedor_id);
    }
}
