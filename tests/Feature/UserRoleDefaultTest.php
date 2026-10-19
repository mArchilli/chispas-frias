<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

/**
 * El rol por defecto es 'cliente': un usuario creado sin rol explícito nunca es administrador.
 * Admin y vendedor se asignan siempre a propósito (seeder, panel de vendedores, factory).
 */
class UserRoleDefaultTest extends TestCase
{
    use RefreshDatabase;

    // --- default de la columna y del modelo -------------------------------------------------

    public function test_un_insert_sin_rol_queda_como_cliente_en_la_base(): void
    {
        DB::table('users')->insert([
            'name' => 'Sin rol', 'email' => 'sin-rol@example.com', 'password' => 'x',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame('cliente', DB::table('users')->where('email', 'sin-rol@example.com')->value('role'));
    }

    public function test_user_create_sin_rol_es_cliente_aun_sin_refrescar(): void
    {
        $user = User::create(['name' => 'X', 'email' => 'x@example.com', 'password' => 'secret-123']);

        $this->assertSame(RolUsuario::Cliente, $user->role);
        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->puedeAccederAlPanel());
        $this->assertSame(RolUsuario::Cliente, $user->fresh()->role);
    }

    public function test_la_migracion_no_toca_a_los_usuarios_existentes(): void
    {
        // Los usuarios con rol explícito conservan el suyo al (re)aplicar el cambio de default.
        $admin = User::factory()->admin()->create();
        $vendedor = User::factory()->vendedor()->create();
        $cliente = User::factory()->create();

        (require database_path('migrations/2026_10_19_120000_change_users_role_default_to_cliente.php'))->up();

        $this->assertSame(3, User::count());
        $this->assertSame(RolUsuario::Admin, $admin->fresh()->role);
        $this->assertSame(RolUsuario::Vendedor, $vendedor->fresh()->role);
        $this->assertSame(RolUsuario::Cliente, $cliente->fresh()->role);
    }

    // --- caminos que crean usuarios ----------------------------------------------------------

    public function test_el_registro_publico_crea_un_cliente(): void
    {
        $this->post('/register', [
            'name' => 'Nueva', 'email' => 'nueva@example.com',
            'password' => 'password-123', 'password_confirmation' => 'password-123',
        ]);

        $this->assertSame(RolUsuario::Cliente, User::where('email', 'nueva@example.com')->firstOrFail()->role);
    }

    public function test_el_alta_de_vendedor_desde_el_panel_crea_un_vendedor(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.sellers.store'), ['name' => 'Vende', 'email' => 'vende@example.com']);

        $this->assertSame(RolUsuario::Vendedor, User::where('email', 'vende@example.com')->firstOrFail()->role);
    }

    public function test_la_factory_es_cliente_por_defecto_y_tiene_estados_explicitos(): void
    {
        $this->assertSame(RolUsuario::Cliente, User::factory()->create()->role);
        $this->assertSame(RolUsuario::Admin, User::factory()->admin()->create()->role);
        $this->assertSame(RolUsuario::Vendedor, User::factory()->vendedor()->create()->role);
    }

    // --- seeders -------------------------------------------------------------------------

    public function test_el_seeder_crea_un_admin_con_el_email_y_la_clave_del_env(): void
    {
        config(['seeders.admin_email' => 'duenio@example.com', 'seeders.admin_password' => 'clave-del-env-1', 'seeders.admin_nombre' => 'Dueño']);

        $this->seed(UserSeeder::class);

        $admin = User::where('email', 'duenio@example.com')->sole();
        $this->assertSame(RolUsuario::Admin, $admin->role);
        $this->assertSame('Dueño', $admin->name);
        $this->assertTrue(Hash::check('clave-del-env-1', $admin->password));
        $this->assertNotSame('clave-del-env-1', $admin->getRawOriginal('password'));
    }

    public function test_sin_clave_en_el_env_el_seeder_genera_una_aleatoria(): void
    {
        config(['seeders.admin_email' => 'duenio@example.com', 'seeders.admin_password' => null]);

        $this->seed(UserSeeder::class);
        $primera = User::where('email', 'duenio@example.com')->sole()->getRawOriginal('password');
        User::query()->delete();
        $this->seed(UserSeeder::class);
        $segunda = User::where('email', 'duenio@example.com')->sole()->getRawOriginal('password');

        $this->assertNotSame($primera, $segunda, 'Cada corrida genera una contraseña distinta.');
        $this->assertFalse(Hash::check('Chispasfrias1-', $segunda), 'No queda ninguna contraseña fija del código.');
    }

    public function test_sin_email_en_el_env_el_seeder_se_niega(): void
    {
        config(['seeders.admin_email' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SEED_ADMIN_EMAIL');

        $this->seed(UserSeeder::class);
    }

    public function test_el_seeder_no_modifica_a_un_usuario_existente(): void
    {
        $existente = User::factory()->create(['email' => 'duenio@example.com', 'name' => 'Original']);
        $hashOriginal = $existente->getRawOriginal('password');
        config(['seeders.admin_email' => 'duenio@example.com', 'seeders.admin_password' => 'otra-clave-99']);

        $this->seed(UserSeeder::class);

        $this->assertSame(1, User::count());
        $this->assertSame('Original', $existente->fresh()->name);
        $this->assertSame(RolUsuario::Cliente, $existente->fresh()->role);
        $this->assertSame($hashOriginal, $existente->fresh()->getRawOriginal('password'));
    }

    public function test_los_seeders_nunca_corren_en_produccion(): void
    {
        config(['seeders.admin_email' => 'duenio@example.com']);
        $this->app['env'] = 'production';

        foreach ([DatabaseSeeder::class, UserSeeder::class] as $seeder) {
            try {
                // Directo, no vía db:seed (que en producción pide confirmación antes de llegar acá).
                $this->app->make($seeder)->run();
                $this->fail("{$seeder} corrió en producción.");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('producción', $e->getMessage());
            }
        }

        $this->assertSame(0, User::count());
    }

    public function test_el_database_seeder_en_desarrollo_crea_el_admin_y_los_datos_de_ejemplo(): void
    {
        config(['seeders.admin_email' => 'duenio@example.com', 'seeders.admin_password' => 'clave-del-env-1']);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(RolUsuario::Admin, User::where('email', 'duenio@example.com')->sole()->role);
        $this->assertGreaterThan(0, \App\Models\Product::count());
    }
}
