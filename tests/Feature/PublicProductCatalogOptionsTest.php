<?php

namespace Tests\Feature;

use App\Enums\AlcanceOferta;
use App\Enums\TipoDescuento;
use App\Models\Addon;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductPriceTier;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicProductCatalogOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_cards_receive_the_same_colors_images_and_unit_pricing_as_the_catalog(): void
    {
        $product = Product::factory()->for(Category::factory())->create([
            'price' => 1000, 'stock' => 200, 'is_active' => true, 'is_featured' => true,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id, 'name' => 'Dorado', 'color_hex' => '#e0bf32',
            'stock' => 100, 'price_addon' => 250,
        ]);
        ProductVariant::factory()->inactive()->create(['product_id' => $product->id]);
        ProductImage::create([
            'product_id' => $product->id, 'product_variant_id' => $variant->id,
            'path' => 'gold.jpg', 'type' => 'image', 'sort_order' => 0, 'is_primary' => true,
        ]);
        ProductPriceTier::factory()->create([
            'product_id' => $product->id, 'cantidad_minima' => 100, 'precio_unitario' => 800,
        ]);
        $product->offers()->create([
            'is_active' => true, 'tipo_descuento' => TipoDescuento::Porcentaje,
            'valor_descuento' => 10, 'alcance' => AlcanceOferta::Todos,
        ]);

        $home = $this->get('/')->assertOk();
        $home->assertInertia(function (Assert $page) use ($product, $variant) {
            $page->component('Welcome');
            foreach (['featuredProducts', 'offerProducts'] as $list) {
                $page->has($list, 1)
                    ->where("{$list}.0.id", $product->id)
                    ->has("{$list}.0.variants", 1)
                    ->where("{$list}.0.variants.0.id", $variant->id)
                    ->where("{$list}.0.variants.0.color_hex", '#e0bf32')
                    ->where("{$list}.0.variants.0.price_addon", 250)
                    ->where("{$list}.0.images.0.product_variant_id", $variant->id)
                    ->where("{$list}.0.pricing.final_price", 900);
            }
        });

        $catalog = $this->get(route('products.index'))->assertOk();
        $this->assertEquals($catalog->inertiaProps('products.data.0'), $home->inertiaProps('featuredProducts.0'));
        $this->assertEquals($home->inertiaProps('featuredProducts.0'), $home->inertiaProps('offerProducts.0'));
    }

    public function test_catalog_exposes_active_colors_with_stock_surcharges_and_associated_images(): void
    {
        $product = Product::factory()->for(Category::factory())->create([
            'price' => 1000, 'stock' => 200, 'is_active' => true, 'is_featured' => true,
        ]);
        $red = ProductVariant::factory()->create([
            'product_id' => $product->id, 'name' => 'Rojo', 'color_hex' => '#ff0000',
            'stock' => 120, 'price_addon' => 250, 'sort_order' => 0,
        ]);
        ProductVariant::factory()->create([
            'product_id' => $product->id, 'name' => 'Azul', 'stock' => 0, 'sort_order' => 1,
        ]);
        ProductVariant::factory()->inactive()->create(['product_id' => $product->id]);
        ProductImage::create([
            'product_id' => $product->id, 'product_variant_id' => $red->id,
            'path' => 'red.jpg', 'type' => 'image', 'sort_order' => 0, 'is_primary' => true,
        ]);
        ProductPriceTier::factory()->create([
            'product_id' => $product->id, 'cantidad_minima' => 100, 'precio_unitario' => 800,
        ]);

        $this->get(route('products.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Products/Index')
            ->has('products.data', 1)
            ->where('products.data.0.is_featured', true)
            ->has('products.data.0.variants', 2)
            ->where('products.data.0.variants.0.id', $red->id)
            ->where('products.data.0.variants.0.color_hex', '#ff0000')
            ->where('products.data.0.variants.0.stock', 120)
            ->where('products.data.0.variants.0.price_addon', 250)
            ->where('products.data.0.variants.1.stock', 0)
            ->where('products.data.0.images.0.product_variant_id', $red->id)
            ->where('products.data.0.price_tiers.0.cantidad_minima', 100)
            ->where('products.data.0.price_tiers.0.precio_unitario', 800)
            ->where('products.data.0.pricing.final_price', 1000)
        );
    }

    public function test_catalog_exposes_custom_colors_and_only_active_personalizations(): void
    {
        $product = Product::factory()->for(Category::factory())->create(['is_active' => true, 'stock' => 100]);
        ProductVariant::factory()->customColor()->create(['product_id' => $product->id]);
        $active = Addon::factory()->conTexto()->create();
        $inactive = Addon::factory()->inactive()->create();
        $product->addons()->attach([$active->id, $inactive->id]);

        $this->get(route('products.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('products.data.0.variants.0.is_custom_color', true)
            ->has('products.data.0.addons', 1)
            ->where('products.data.0.addons.0.id', $active->id)
            ->where('products.data.0.addons.0.requires_text', true)
        );
    }

    public function test_catalog_keeps_products_without_options_compatible(): void
    {
        Product::factory()->for(Category::factory())->create(['is_active' => true, 'stock' => 100]);

        $this->get(route('products.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('products.data.0.variants', 0)
            ->has('products.data.0.addons', 0)
            ->has('products.data.0.price_tiers', 0)
            ->where('products.data.0.current_offer', null)
        );
    }
}
