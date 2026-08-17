<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Fase B6: el catálogo público y los productos relacionados de la ficha usan
 * Product::scopeInStock() para no mostrar productos con stock <= 0 como si
 * estuvieran comprables. La ficha individual (show() para el producto pedido
 * directamente por URL) es la única excepción: sigue siendo accesible sin
 * stock, para poder mostrar el estado "Sin stock" en vez de un 404.
 */
class PublicCatalogStockVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_excluye_productos_sin_stock_e_incluye_productos_con_stock(): void
    {
        $category = Category::factory()->create();
        $sinStock = Product::factory()->for($category)->create(['is_active' => true, 'stock' => 0]);
        $conStock = Product::factory()->for($category)->create(['is_active' => true, 'stock' => 5]);

        $response = $this->get(route('products.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Products/Index')
            ->where('products.data', function ($data) use ($sinStock, $conStock) {
                $ids = collect($data)->pluck('id')->all();

                $this->assertNotContains($sinStock->id, $ids, 'El catálogo no debe listar productos sin stock.');
                $this->assertContains($conStock->id, $ids, 'El catálogo debe listar productos con stock disponible.');

                return true;
            })
        );
    }

    public function test_show_producto_sin_stock_sigue_siendo_accesible_por_url(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create(['is_active' => true, 'stock' => 0]);

        $response = $this->get(route('products.show', $product));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Products/Show')
            ->where('product.id', $product->id)
            ->where('product.stock', 0)
        );
    }

    public function test_show_no_filtra_el_producto_pedido_aunque_este_sin_stock_pero_si_filtra_sus_relacionados(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create(['is_active' => true, 'stock' => 10]);
        $relacionadoSinStock = Product::factory()->for($category)->create(['is_active' => true, 'stock' => 0]);
        $relacionadoConStock = Product::factory()->for($category)->create(['is_active' => true, 'stock' => 5]);

        $response = $this->get(route('products.show', $product));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Products/Show')
            ->where('relatedProducts', function ($relatedProducts) use ($relacionadoSinStock, $relacionadoConStock) {
                $ids = collect($relatedProducts)->pluck('id')->all();

                $this->assertNotContains(
                    $relacionadoSinStock->id,
                    $ids,
                    'Los productos relacionados no deben incluir productos sin stock.'
                );
                $this->assertContains(
                    $relacionadoConStock->id,
                    $ids,
                    'Los productos relacionados deben incluir productos con stock disponible.'
                );

                return true;
            })
        );
    }
}
