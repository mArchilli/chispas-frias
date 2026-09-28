<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProductVariantAddonSyncTest extends TestCase
{
    use RefreshDatabase;

    private function baseProductData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Fantasia Dorada',
            'description' => 'Una fantasia dorada.',
            'price' => 100,
            'category_id' => Category::factory()->create()->id,
            'stock' => 50,
            'is_active' => '1',
            'is_featured' => '0',
        ], $overrides);
    }

    // --- store ---------------------------------------------------------------

    public function test_store_crea_producto_con_variantes_y_addons(): void
    {
        $admin = User::factory()->create();
        $addon = Addon::create(['name' => 'Grabado', 'price' => 500, 'is_active' => true]);

        $this->actingAs($admin)->post(route('admin.products.store'), $this->baseProductData([
            'variants' => [
                ['_uid' => 'v-1', 'name' => 'Rojo', 'color_hex' => '#ff0000', 'price_addon' => 250, 'stock' => 8, 'is_active' => '1', 'is_custom_color' => '0'],
                ['_uid' => 'v-2', 'name' => 'A elección', 'color_hex' => '#000000', 'price_addon' => '', 'stock' => '', 'is_active' => '1', 'is_custom_color' => '1'],
            ],
            'addons' => [
                ['id' => $addon->id, 'price_override' => 700],
            ],
        ]))->assertRedirect(route('admin.products.index'));

        $product = Product::firstOrFail();
        $this->assertCount(2, $product->variants);

        $rojo = $product->variants->firstWhere('name', 'Rojo');
        $this->assertEquals(250.0, (float) $rojo->price_addon);
        $this->assertSame(8, $rojo->stock);
        $this->assertFalse($rojo->is_custom_color);

        $custom = $product->variants->firstWhere('name', 'A elección');
        $this->assertTrue($custom->is_custom_color);
        $this->assertNull($custom->stock);                       // '' ⇒ ilimitado
        $this->assertEquals(0.0, (float) $custom->price_addon);

        $this->assertCount(1, $product->addons);
        $this->assertEquals(700.0, (float) $product->addons->first()->pivot->price_override);
    }

    public function test_store_rechaza_dos_variantes_color_a_eleccion(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.products.store'), $this->baseProductData([
            'variants' => [
                ['name' => 'Libre 1', 'is_custom_color' => '1'],
                ['name' => 'Libre 2', 'is_custom_color' => '1'],
            ],
        ]))->assertSessionHasErrors('variants');

        $this->assertSame(0, Product::count());
    }

    public function test_store_rechaza_nombres_de_variante_duplicados(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.products.store'), $this->baseProductData([
            'variants' => [
                ['name' => 'Rojo', 'is_custom_color' => '0'],
                ['name' => 'rojo', 'is_custom_color' => '0'],
            ],
        ]))->assertSessionHasErrors('variants.1.name');

        $this->assertSame(0, Product::count());
    }

    public function test_store_rechaza_addon_inexistente(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.products.store'), $this->baseProductData([
            'addons' => [['id' => 999999]],
        ]))->assertSessionHasErrors('addons.0.id');
    }

    // --- update -------------------------------------------------------------

    public function test_update_sincroniza_variantes_full_sync(): void
    {
        $admin = User::factory()->create();
        $product = Product::factory()->create(['price' => 100]);

        $conservar = ProductVariant::factory()->create([
            'product_id' => $product->id, 'name' => 'Rojo', 'price_addon' => 100, 'is_custom_color' => false,
        ]);
        $eliminar = ProductVariant::factory()->create([
            'product_id' => $product->id, 'name' => 'Verde', 'is_custom_color' => false,
        ]);

        $this->actingAs($admin)->put(route('admin.products.update', $product), $this->baseProductData([
            'variants' => [
                ['id' => $conservar->id, 'name' => 'Rojo', 'color_hex' => '#ff0000', 'price_addon' => 150, 'stock' => 5, 'is_active' => '1', 'is_custom_color' => '0'],
                ['name' => 'Azul', 'color_hex' => '#0000ff', 'price_addon' => 0, 'stock' => '', 'is_active' => '1', 'is_custom_color' => '0'],
            ],
        ]))->assertRedirect(route('admin.products.index'));

        $product->refresh();
        $this->assertNull(ProductVariant::find($eliminar->id));
        $this->assertCount(2, $product->variants);
        $this->assertEquals(150.0, (float) $conservar->fresh()->price_addon);
        $this->assertNotNull($product->variants->firstWhere('name', 'Azul'));
    }

    public function test_update_sincroniza_addons_attach_detach_y_override(): void
    {
        $admin = User::factory()->create();
        $product = Product::factory()->create();
        $viejo = Addon::create(['name' => 'Viejo', 'price' => 100, 'is_active' => true]);
        $nuevo = Addon::create(['name' => 'Nuevo', 'price' => 200, 'is_active' => true]);
        $product->addons()->attach($viejo->id, ['sort_order' => 0]);

        $this->actingAs($admin)->put(route('admin.products.update', $product), $this->baseProductData([
            'addons' => [
                ['id' => $nuevo->id, 'price_override' => 250],
            ],
        ]))->assertRedirect();

        $product->refresh();
        $this->assertEquals([$nuevo->id], $product->addons->pluck('id')->all());
        $this->assertEquals(250.0, (float) $product->addons->first()->pivot->price_override);
    }

    public function test_update_asocia_imagen_existente_a_una_variante_nueva_via_uid(): void
    {
        $admin = User::factory()->create();
        $product = Product::factory()->create();
        $image = ProductImage::create([
            'product_id' => $product->id,
            'path' => 'foto.jpg',
            'type' => 'image',
            'sort_order' => 1,
            'is_primary' => true,
        ]);

        $this->actingAs($admin)->put(route('admin.products.update', $product), $this->baseProductData([
            'variants' => [
                ['_uid' => 'nueva-rojo', 'name' => 'Rojo', 'color_hex' => '#ff0000', 'is_active' => '1', 'is_custom_color' => '0'],
            ],
            'existing_images_variant' => [
                (string) $image->id => 'uid:nueva-rojo',
            ],
        ]))->assertRedirect();

        $variante = $product->fresh()->variants->firstWhere('name', 'Rojo');
        $this->assertNotNull($variante);
        $this->assertSame($variante->id, $image->fresh()->product_variant_id);
    }

    public function test_update_reasigna_imagen_a_variante_existente_y_permite_desasociar(): void
    {
        $admin = User::factory()->create();
        $product = Product::factory()->create();
        $variante = ProductVariant::factory()->create(['product_id' => $product->id, 'name' => 'Rojo']);
        $image = ProductImage::create([
            'product_id' => $product->id,
            'product_variant_id' => $variante->id,
            'path' => 'foto.jpg',
            'type' => 'image',
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)->put(route('admin.products.update', $product), $this->baseProductData([
            'variants' => [
                ['id' => $variante->id, 'name' => 'Rojo', 'is_active' => '1', 'is_custom_color' => '0'],
            ],
            'existing_images_variant' => [
                (string) $image->id => '',
            ],
        ]))->assertRedirect();

        $this->assertNull($image->fresh()->product_variant_id);
    }

    public function test_update_rechaza_id_de_variante_de_otro_producto(): void
    {
        $admin = User::factory()->create();
        $product = Product::factory()->create();
        $otro = Product::factory()->create();
        $ajena = ProductVariant::factory()->create(['product_id' => $otro->id, 'name' => 'Ajena']);

        $this->actingAs($admin)->put(route('admin.products.update', $product), $this->baseProductData([
            'variants' => [
                ['id' => $ajena->id, 'name' => 'Ajena', 'is_active' => '1', 'is_custom_color' => '0'],
            ],
        ]))->assertSessionHasErrors('variants.0.id');
    }
}
