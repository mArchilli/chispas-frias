<?php

namespace Tests\Feature;

use App\Enums\AlcanceOferta;
use App\Enums\RolUsuario;
use App\Enums\TipoDescuento;
use App\Models\Category;
use App\Models\DiscountCode;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    private function crearUsuario(RolUsuario $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    /**
     * Secciones operativas de /admin: visibles para admin y vendedor por igual.
     */
    private function rutasCompartidas(): array
    {
        return [
            route('admin.dashboard'),
            route('admin.orders.index'),
            route('admin.products.index'),
            route('admin.categories.index'),
            route('admin.offers.index'),
            route('admin.discount-codes.index'),
        ];
    }

    /**
     * Secciones exclusivas de admin (Gates 'gestionar-vendedores' y
     * 'gestionar-configuracion').
     */
    private function rutasExclusivasDeAdmin(): array
    {
        return [
            route('admin.sellers.index'),
            route('admin.settings.edit'),
        ];
    }

    // --- cliente: sin acceso a nada de /admin -----------------------------------

    public function test_cliente_recibe_403_en_todas_las_secciones_del_admin(): void
    {
        $cliente = $this->crearUsuario(RolUsuario::Cliente);

        foreach ([...$this->rutasCompartidas(), ...$this->rutasExclusivasDeAdmin()] as $ruta) {
            $this->actingAs($cliente)->get($ruta)->assertForbidden();
        }
    }

    // --- vendedor: acceso operativo, sin vendedores ni configuración -------------

    public function test_vendedor_accede_a_las_secciones_operativas(): void
    {
        $vendedor = $this->crearUsuario(RolUsuario::Vendedor);

        foreach ($this->rutasCompartidas() as $ruta) {
            $this->actingAs($vendedor)->get($ruta)->assertOk();
        }
    }

    public function test_vendedor_recibe_403_en_vendedores_y_configuracion(): void
    {
        $vendedor = $this->crearUsuario(RolUsuario::Vendedor);

        foreach ($this->rutasExclusivasDeAdmin() as $ruta) {
            $this->actingAs($vendedor)->get($ruta)->assertForbidden();
        }
    }

    // --- admin: acceso total, sin excepciones ------------------------------------

    public function test_admin_accede_a_todas_las_secciones_del_admin(): void
    {
        $admin = $this->crearUsuario(RolUsuario::Admin);

        foreach ([...$this->rutasCompartidas(), ...$this->rutasExclusivasDeAdmin()] as $ruta) {
            $this->actingAs($admin)->get($ruta)->assertOk();
        }
    }

    // --- vendedor: no puede borrar catálogo, pero sí crear/editar/togglear ------

    public function test_vendedor_no_puede_borrar_una_categoria_pero_si_puede_administrarla(): void
    {
        $vendedor = $this->crearUsuario(RolUsuario::Vendedor);
        $category = Category::factory()->create();

        $this->actingAs($vendedor)
            ->post(route('admin.categories.store'), [
                'name' => 'Nueva Categoría',
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.categories.index'));
        $this->assertDatabaseHas('categories', ['name' => 'Nueva Categoría']);

        $this->actingAs($vendedor)
            ->put(route('admin.categories.update', $category), [
                'name' => 'Categoría Editada',
                'is_active' => true,
            ])
            ->assertRedirect();
        $this->assertSame('Categoría Editada', $category->fresh()->name);

        $this->actingAs($vendedor)
            ->patch(route('admin.categories.toggle-status', $category))
            ->assertRedirect();

        $this->actingAs($vendedor)
            ->delete(route('admin.categories.destroy', $category))
            ->assertForbidden();
        $this->assertNotNull($category->fresh());
    }

    public function test_vendedor_no_puede_borrar_un_producto_pero_si_puede_administrarlo(): void
    {
        $vendedor = $this->crearUsuario(RolUsuario::Vendedor);
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);

        $this->actingAs($vendedor)
            ->post(route('admin.products.store'), [
                'title' => 'Producto nuevo',
                'description' => 'Descripción',
                'price' => 100,
                'category_id' => $category->id,
            ])
            ->assertRedirect(route('admin.products.index'));
        $this->assertDatabaseHas('products', ['title' => 'Producto nuevo']);

        $this->actingAs($vendedor)
            ->put(route('admin.products.update', $product), [
                'title' => 'Producto editado',
                'description' => $product->description,
                'price' => $product->price,
                'category_id' => $category->id,
            ])
            ->assertRedirect();
        $this->assertSame('Producto editado', $product->fresh()->title);

        $this->actingAs($vendedor)
            ->patch(route('admin.products.toggle-status', $product))
            ->assertRedirect();

        $this->actingAs($vendedor)
            ->delete(route('admin.products.destroy', $product))
            ->assertForbidden();
        $this->assertNotNull($product->fresh());
    }

    public function test_vendedor_no_puede_borrar_una_oferta_pero_si_puede_administrarla(): void
    {
        $vendedor = $this->crearUsuario(RolUsuario::Vendedor);
        $product = Product::factory()->create(['price' => 100]);
        $offer = ProductOffer::create([
            'product_id' => $product->id,
            'tipo_descuento' => TipoDescuento::Porcentaje,
            'valor_descuento' => 10,
            'alcance' => AlcanceOferta::Todos,
            'is_active' => true,
        ]);

        $this->actingAs($vendedor)
            ->post(route('admin.offers.store'), [
                'product_id' => $product->id,
                'tipo_descuento' => TipoDescuento::Porcentaje->value,
                'valor_descuento' => 15,
                'alcance' => AlcanceOferta::Todos->value,
                'is_active' => false,
            ])
            ->assertRedirect(route('admin.offers.index'));
        $this->assertSame(2, ProductOffer::count());

        $this->actingAs($vendedor)
            ->put(route('admin.offers.update', $offer), [
                'tipo_descuento' => TipoDescuento::Fijo->value,
                'valor_descuento' => 20,
                'alcance' => AlcanceOferta::Todos->value,
                'is_active' => true,
            ])
            ->assertRedirect();
        $this->assertSame(TipoDescuento::Fijo, $offer->fresh()->tipo_descuento);

        $this->actingAs($vendedor)
            ->post(route('admin.offers.toggle-status', $offer))
            ->assertRedirect();

        $this->actingAs($vendedor)
            ->delete(route('admin.offers.destroy', $offer))
            ->assertForbidden();
        $this->assertNotNull($offer->fresh());
    }

    public function test_vendedor_no_puede_borrar_un_codigo_de_descuento_pero_si_puede_administrarlo(): void
    {
        $vendedor = $this->crearUsuario(RolUsuario::Vendedor);
        $discountCode = DiscountCode::create([
            'code' => 'VENDEDORTEST',
            'percentage' => 10,
            'is_active' => true,
        ]);

        $this->actingAs($vendedor)
            ->post(route('admin.discount-codes.store'), [
                'code' => 'NUEVOCODIGO',
                'percentage' => 20,
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.discount-codes.index'));
        $this->assertDatabaseHas('discount_codes', ['code' => 'NUEVOCODIGO']);

        $this->actingAs($vendedor)
            ->put(route('admin.discount-codes.update', $discountCode), [
                'code' => $discountCode->code,
                'percentage' => 30,
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.discount-codes.index'));
        $this->assertEquals(30.0, (float) $discountCode->fresh()->percentage);

        $this->actingAs($vendedor)
            ->patch(route('admin.discount-codes.toggle-status', $discountCode))
            ->assertRedirect();

        $this->actingAs($vendedor)
            ->delete(route('admin.discount-codes.destroy', $discountCode))
            ->assertForbidden();
        $this->assertNotNull($discountCode->fresh());
    }

    // --- admin: puede borrar catálogo en las cuatro secciones --------------------

    public function test_admin_puede_borrar_catalogo_en_las_cuatro_secciones(): void
    {
        $admin = $this->crearUsuario(RolUsuario::Admin);

        $category = Category::factory()->create();
        $this->actingAs($admin)->delete(route('admin.categories.destroy', $category))->assertRedirect();
        $this->assertNull($category->fresh());

        $product = Product::factory()->create();
        $this->actingAs($admin)->delete(route('admin.products.destroy', $product))->assertRedirect();
        $this->assertNull($product->fresh());

        $offerProduct = Product::factory()->create();
        $offer = ProductOffer::create([
            'product_id' => $offerProduct->id,
            'tipo_descuento' => TipoDescuento::Porcentaje,
            'valor_descuento' => 10,
            'alcance' => AlcanceOferta::Todos,
            'is_active' => true,
        ]);
        $this->actingAs($admin)->delete(route('admin.offers.destroy', $offer))->assertRedirect();
        $this->assertNull($offer->fresh());

        $discountCode = DiscountCode::create([
            'code' => 'ADMINBORRA',
            'percentage' => 10,
            'is_active' => true,
        ]);
        $this->actingAs($admin)->delete(route('admin.discount-codes.destroy', $discountCode))->assertRedirect();
        $this->assertNull($discountCode->fresh());
    }

    // --- registro público: siempre cliente, nunca admin --------------------------

    public function test_registro_publico_crea_una_cuenta_cliente_sin_acceso_al_admin(): void
    {
        $response = $this->post('/register', [
            'name' => 'Cliente Nuevo',
            'email' => 'cliente-nuevo@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();

        $user = User::where('email', 'cliente-nuevo@example.com')->firstOrFail();
        $this->assertSame(RolUsuario::Cliente, $user->role);

        $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
    }
}
