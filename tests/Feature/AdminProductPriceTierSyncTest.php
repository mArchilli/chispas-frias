<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPriceTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProductPriceTierSyncTest extends TestCase
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

    public function test_store_crea_producto_con_escalas_de_precio(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.products.store'), $this->baseProductData([
            'price_tiers' => [
                ['cantidad_minima' => 5, 'precio_unitario' => 90],
                ['cantidad_minima' => 10, 'precio_unitario' => 80],
            ],
        ]));

        $response->assertRedirect(route('admin.products.index'));

        $product = Product::firstOrFail();
        $this->assertCount(2, $product->priceTiers);
        $this->assertEquals(90.0, (float) $product->priceTiers->firstWhere('cantidad_minima', 5)->precio_unitario);
        $this->assertEquals(80.0, (float) $product->priceTiers->firstWhere('cantidad_minima', 10)->precio_unitario);
    }

    public function test_store_sin_price_tiers_sigue_funcionando_igual_que_antes(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.products.store'), $this->baseProductData());

        $response->assertRedirect(route('admin.products.index'));

        $product = Product::firstOrFail();
        $this->assertCount(0, $product->priceTiers);
    }

    public function test_store_rechaza_cantidad_minima_duplicada(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.products.store'), $this->baseProductData([
            'price_tiers' => [
                ['cantidad_minima' => 5, 'precio_unitario' => 90],
                ['cantidad_minima' => 5, 'precio_unitario' => 85],
            ],
        ]));

        $response->assertSessionHasErrors();
        $this->assertSame(0, Product::count());
    }

    public function test_store_rechaza_cantidad_minima_menor_o_igual_a_uno(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.products.store'), $this->baseProductData([
            'price_tiers' => [
                ['cantidad_minima' => 1, 'precio_unitario' => 90],
            ],
        ]));

        $response->assertSessionHasErrors('price_tiers.0.cantidad_minima');
        $this->assertSame(0, Product::count());
    }

    public function test_update_sincroniza_agregar_editar_y_quitar_en_el_mismo_submit(): void
    {
        $admin = User::factory()->create();
        $product = Product::factory()->create(['price' => 100]);

        $tierAConservar = ProductPriceTier::factory()->create([
            'product_id' => $product->id,
            'cantidad_minima' => 5,
            'precio_unitario' => 90,
        ]);
        $tierAEliminar = ProductPriceTier::factory()->create([
            'product_id' => $product->id,
            'cantidad_minima' => 10,
            'precio_unitario' => 80,
        ]);

        $response = $this->actingAs($admin)->put(route('admin.products.update', $product), $this->baseProductData([
            'price_tiers' => [
                // Editar la existente (mismo id, nuevo precio)
                ['id' => $tierAConservar->id, 'cantidad_minima' => 5, 'precio_unitario' => 88],
                // Nueva fila (sin id)
                ['cantidad_minima' => 20, 'precio_unitario' => 70],
                // tierAEliminar no viene en el array => debe borrarse
            ],
        ]));

        $response->assertRedirect(route('admin.products.index'));

        $product->refresh();
        $tiers = $product->priceTiers()->orderBy('cantidad_minima')->get();

        $this->assertCount(2, $tiers);
        $this->assertNull(ProductPriceTier::find($tierAEliminar->id));

        $this->assertSame($tierAConservar->id, $tiers->first()->id);
        $this->assertEquals(88.0, (float) $tiers->first()->precio_unitario);

        $this->assertEquals(20, $tiers->last()->cantidad_minima);
        $this->assertEquals(70.0, (float) $tiers->last()->precio_unitario);
    }

    public function test_update_sin_la_clave_price_tiers_no_toca_las_escalas_existentes(): void
    {
        $admin = User::factory()->create();
        $product = Product::factory()->create(['price' => 100]);
        ProductPriceTier::factory()->create(['product_id' => $product->id, 'cantidad_minima' => 5, 'precio_unitario' => 90]);

        $data = $this->baseProductData();
        unset($data['price_tiers']);

        $response = $this->actingAs($admin)->put(route('admin.products.update', $product), $data);

        $response->assertRedirect(route('admin.products.index'));
        $this->assertCount(1, $product->fresh()->priceTiers);
    }

    public function test_update_con_price_tiers_vacio_borra_todas_las_escalas_existentes(): void
    {
        $admin = User::factory()->create();
        $product = Product::factory()->create(['price' => 100]);
        ProductPriceTier::factory()->create(['product_id' => $product->id, 'cantidad_minima' => 5, 'precio_unitario' => 90]);

        $response = $this->actingAs($admin)->put(route('admin.products.update', $product), $this->baseProductData([
            'price_tiers' => [],
        ]));

        $response->assertRedirect(route('admin.products.index'));
        $this->assertCount(0, $product->fresh()->priceTiers);
    }

    public function test_update_rechaza_id_de_tier_que_pertenece_a_otro_producto(): void
    {
        $admin = User::factory()->create();
        $product = Product::factory()->create(['price' => 100]);
        $otroProducto = Product::factory()->create(['price' => 50]);
        $tierAjeno = ProductPriceTier::factory()->create([
            'product_id' => $otroProducto->id,
            'cantidad_minima' => 5,
            'precio_unitario' => 40,
        ]);

        $response = $this->actingAs($admin)->put(route('admin.products.update', $product), $this->baseProductData([
            'price_tiers' => [
                ['id' => $tierAjeno->id, 'cantidad_minima' => 5, 'precio_unitario' => 40],
            ],
        ]));

        $response->assertSessionHasErrors('price_tiers.0.id');
    }
}
