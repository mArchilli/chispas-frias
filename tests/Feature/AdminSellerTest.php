<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminSellerTest extends TestCase
{
    use RefreshDatabase;

    private function crearVendedor(array $overrides = []): User
    {
        return User::factory()->create(array_merge(['role' => RolUsuario::Vendedor], $overrides));
    }

    // --- admin: ABM completo de vendedores ---------------------------------------

    public function test_admin_puede_listar_vendedores(): void
    {
        $admin = User::factory()->create();
        $seller = $this->crearVendedor(['name' => 'Vendedor Uno']);
        // Un cliente no debería aparecer en el listado de vendedores.
        User::factory()->create(['role' => RolUsuario::Cliente]);

        $response = $this->actingAs($admin)->get(route('admin.sellers.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Sellers/Index')
            ->has('sellers', 1)
            ->where('sellers.0.id', $seller->id)
        );
    }

    public function test_admin_puede_crear_un_vendedor(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.sellers.store'), [
            'name' => 'Nuevo Vendedor',
            'email' => 'nuevo-vendedor@example.com',
        ]);

        $response->assertRedirect(route('admin.sellers.index'));

        $seller = User::where('email', 'nuevo-vendedor@example.com')->firstOrFail();
        $this->assertSame(RolUsuario::Vendedor, $seller->role);
        $this->assertTrue($seller->is_active);
        $this->assertNotNull($seller->email_verified_at);
        // La contraseña temporal viaja una única vez por flash, no se persiste en claro.
        $this->assertNotEmpty(session('temporaryPassword'));
    }

    public function test_admin_puede_editar_un_vendedor(): void
    {
        $admin = User::factory()->create();
        $seller = $this->crearVendedor(['name' => 'Nombre Viejo']);

        $response = $this->actingAs($admin)->put(route('admin.sellers.update', $seller), [
            'name' => 'Nombre Nuevo',
            'email' => $seller->email,
        ]);

        $response->assertRedirect(route('admin.sellers.index'));
        $this->assertSame('Nombre Nuevo', $seller->fresh()->name);
    }

    public function test_admin_puede_activar_y_desactivar_un_vendedor(): void
    {
        $admin = User::factory()->create();
        $seller = $this->crearVendedor(['is_active' => true]);

        $response = $this->actingAs($admin)->patch(route('admin.sellers.toggle-status', $seller));
        $response->assertRedirect();
        $this->assertFalse($seller->fresh()->is_active);

        $response = $this->actingAs($admin)->patch(route('admin.sellers.toggle-status', $seller));
        $response->assertRedirect();
        $this->assertTrue($seller->fresh()->is_active);
    }

    // --- vendedor: sin acceso al ABM de vendedores --------------------------------

    public function test_un_vendedor_no_puede_acceder_a_ninguna_ruta_de_gestion_de_vendedores(): void
    {
        $vendedor = $this->crearVendedor();
        $otroVendedor = $this->crearVendedor();

        $this->actingAs($vendedor)->get(route('admin.sellers.index'))->assertForbidden();
        $this->actingAs($vendedor)->get(route('admin.sellers.create'))->assertForbidden();
        $this->actingAs($vendedor)->post(route('admin.sellers.store'), [
            'name' => 'Intento',
            'email' => 'intento@example.com',
        ])->assertForbidden();
        $this->actingAs($vendedor)->get(route('admin.sellers.edit', $otroVendedor))->assertForbidden();
        $this->actingAs($vendedor)->put(route('admin.sellers.update', $otroVendedor), [
            'name' => 'Hackeado',
            'email' => $otroVendedor->email,
        ])->assertForbidden();
        $this->actingAs($vendedor)->patch(route('admin.sellers.toggle-status', $otroVendedor))->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'intento@example.com']);
        $this->assertNotSame('Hackeado', $otroVendedor->fresh()->name);
    }

    // --- login: una cuenta desactivada no puede entrar ----------------------------

    public function test_una_cuenta_desactivada_no_puede_loguearse_aunque_la_contrasena_sea_correcta(): void
    {
        $seller = $this->crearVendedor(['is_active' => false]);

        $response = $this->post('/login', [
            'email' => $seller->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_una_cuenta_reactivada_puede_volver_a_loguearse(): void
    {
        $seller = $this->crearVendedor(['is_active' => false]);

        $seller->update(['is_active' => true]);

        $response = $this->post('/login', [
            'email' => $seller->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($seller);
        $response->assertRedirect(route('admin.dashboard', absolute: false));
    }
}
