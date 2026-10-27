<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * CHK-2c — el admin elige, en la ficha del producto, qué productos son compatibles con él y si se «sugiere siempre» en el carrito.
 * La relación es simétrica: se guarda una vez y se ve desde los dos productos (Product::sincronizarCompatibles).
 */
class AdminProductCompatiblesTest extends TestCase
{
    use RefreshDatabase;

    private function producto(string $titulo, array $extra = []): Product
    {
        return Product::factory()->for(Category::factory()->create())->create(array_merge([
            'title' => $titulo,
            'price' => 1000,
            'is_active' => true,
        ], $extra));
    }

    private function datos(Product $producto, array $overrides = []): array
    {
        return array_merge([
            'title' => $producto->title,
            'description' => 'Descripción.',
            'price' => 1000,
            'category_id' => $producto->category_id,
            'stock' => 10,
            'is_active' => '1',
            'is_featured' => '0',
        ], $overrides);
    }

    public function test_el_admin_guarda_compatibles_y_sugerir_siempre_al_editar_un_producto(): void
    {
        $admin = User::factory()->admin()->create();
        $producto = $this->producto('Producto');
        $a = $this->producto('A');
        $b = $this->producto('B');

        $this->actingAs($admin)->put(route('admin.products.update', $producto), $this->datos($producto, [
            'sugerir_en_carrito' => '1',
            'compatibles' => [$b->id, $a->id],
        ]))->assertSessionHasNoErrors();

        $producto->refresh();
        $this->assertTrue($producto->sugerir_en_carrito);
        $this->assertSame([$b->id, $a->id], $producto->idsCompatibles());
        // Y desde el otro lado también se ve.
        $this->assertSame([$producto->id], $a->idsCompatibles());
    }

    public function test_el_alta_acepta_compatibles(): void
    {
        $admin = User::factory()->admin()->create();
        $existente = $this->producto('Existente');
        $datos = $this->datos($existente, ['title' => 'Nuevo', 'compatibles' => [$existente->id], 'sugerir_en_carrito' => '1']);

        $this->actingAs($admin)->post(route('admin.products.store'), $datos)->assertSessionHasNoErrors();

        $nuevo = Product::where('title', 'Nuevo')->firstOrFail();
        $this->assertSame([$existente->id], $nuevo->idsCompatibles());
        $this->assertTrue($nuevo->sugerir_en_carrito);
        $this->assertSame([$nuevo->id], $existente->idsCompatibles());
    }

    public function test_sin_el_campo_se_quitan_todos_y_se_apaga_sugerir_siempre(): void
    {
        // El formulario manda el set completo: sin ningún compatible elegido el campo no viaja.
        $admin = User::factory()->admin()->create();
        $producto = $this->producto('Producto', ['sugerir_en_carrito' => true]);
        $otro = $this->producto('Otro');
        $producto->sincronizarCompatibles([$otro->id]);

        $this->actingAs($admin)->put(route('admin.products.update', $producto), $this->datos($producto))->assertSessionHasNoErrors();

        $this->assertSame([], $producto->fresh()->idsCompatibles());
        $this->assertSame([], $otro->idsCompatibles());
        $this->assertFalse($producto->fresh()->sugerir_en_carrito);
    }

    public function test_quitar_un_compatible_desde_el_otro_producto_lo_desvincula_en_ambos_sentidos(): void
    {
        $admin = User::factory()->admin()->create();
        $pistola = $this->producto('Pistola');
        $chispa = $this->producto('Chispa');
        $pistola->sincronizarCompatibles([$chispa->id]);

        // La chispa se edita sin la pistola (el par estaba guardado en el otro sentido).
        $this->actingAs($admin)->put(route('admin.products.update', $chispa), $this->datos($chispa))->assertSessionHasNoErrors();

        $this->assertSame([], $pistola->idsCompatibles());
        $this->assertSame([], $chispa->idsCompatibles());
    }

    public function test_un_producto_no_puede_ser_compatible_consigo_mismo(): void
    {
        $admin = User::factory()->admin()->create();
        $producto = $this->producto('Producto');

        $this->actingAs($admin)->put(route('admin.products.update', $producto), $this->datos($producto, ['compatibles' => [$producto->id]]))
            ->assertSessionHasErrors('compatibles.0');
    }

    public function test_rechaza_ids_de_productos_que_no_existen(): void
    {
        $admin = User::factory()->admin()->create();
        $producto = $this->producto('Producto');

        $this->actingAs($admin)->put(route('admin.products.update', $producto), $this->datos($producto, ['compatibles' => [999999]]))
            ->assertSessionHasErrors('compatibles.0');
    }

    public function test_la_pantalla_de_edicion_trae_los_compatibles_y_el_catalogo_para_el_buscador(): void
    {
        $admin = User::factory()->admin()->create();
        $producto = $this->producto('Producto', ['sugerir_en_carrito' => true]);
        $otro = $this->producto('Otro');
        $tercero = $this->producto('Tercero', ['is_active' => false]);
        $producto->sincronizarCompatibles([$otro->id]);

        $this->actingAs($admin)->get(route('admin.products.edit', $producto))->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Products/Edit')
            ->where('product.compatibles', [$otro->id])
            ->where('product.sugerir_en_carrito', true)
            // El catálogo no incluye al propio producto e incluye los ocultos (el admin tiene que poder verlos y sacarlos).
            ->where('compatibleProducts', fn ($lista) => collect($lista)->pluck('id')->sort()->values()->all() === collect([$otro->id, $tercero->id])->sort()->values()->all())
        );
    }

    public function test_el_vendedor_no_puede_editar_compatibles(): void
    {
        $vendedor = User::factory()->vendedor()->create();
        $producto = $this->producto('Producto');
        $otro = $this->producto('Otro');

        $this->actingAs($vendedor)->put(route('admin.products.update', $producto), $this->datos($producto, ['compatibles' => [$otro->id]]))
            ->assertForbidden();

        $this->assertSame([], $producto->idsCompatibles());
    }

    public function test_borrar_un_producto_limpia_sus_pares(): void
    {
        $admin = User::factory()->admin()->create();
        $pistola = $this->producto('Pistola');
        $chispa = $this->producto('Chispa');
        $pistola->sincronizarCompatibles([$chispa->id]);

        $this->actingAs($admin)->delete(route('admin.products.destroy', $pistola))->assertRedirect();

        $this->assertSame([], $chispa->idsCompatibles());
    }
}
