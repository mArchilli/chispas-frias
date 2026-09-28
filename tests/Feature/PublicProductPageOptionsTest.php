<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * ProductController@show (ficha pública) ahora expone las variantes de color y
 * los add-ons ACTIVOS del producto — para el selector de color, la galería
 * reactiva por variante y el desglose de precio en vivo (resources/js/utils/
 * productOptions.js + pricing.js) — más `product_variant_id` en cada imagen.
 *
 * Un producto sin variantes ni add-ons manda arrays vacíos y la ficha se
 * comporta igual que antes de este sistema.
 */
class PublicProductPageOptionsTest extends TestCase
{
    use RefreshDatabase;

    private function producto(array $attrs = []): Product
    {
        return Product::factory()
            ->for(Category::factory())
            ->create(array_merge(['is_active' => true, 'stock' => 20], $attrs));
    }

    public function test_show_expone_solo_las_variantes_activas_con_recargo_y_stock(): void
    {
        $product = $this->producto();

        $rojo = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Rojo',
            'color_hex' => '#ff0000',
            'price_addon' => 250,
            'stock' => 8,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        ProductVariant::factory()->stockIlimitado()->create([
            'product_id' => $product->id,
            'name' => 'Azul',
            'price_addon' => 0,
            'sort_order' => 1,
            'is_active' => true,
        ]);
        ProductVariant::factory()->inactive()->create([
            'product_id' => $product->id,
            'name' => 'Descontinuado',
            'sort_order' => 2,
        ]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Products/Show')
                ->has('product.variants', 2)
                ->where('product.variants.0.id', $rojo->id)
                ->where('product.variants.0.name', 'Rojo')
                ->where('product.variants.0.color_hex', '#ff0000')
                ->where('product.variants.0.price_addon', 250)
                ->where('product.variants.0.stock', 8)
                ->where('product.variants.0.is_custom_color', false)
                ->where('product.variants.0.is_active', true)
                ->where('product.variants.1.name', 'Azul')
                ->whereNull('product.variants.1.stock') // ilimitado
            );
    }

    public function test_show_expone_la_variante_a_eleccion_del_cliente(): void
    {
        $product = $this->producto();
        ProductVariant::factory()->customColor()->create([
            'product_id' => $product->id,
            'name' => 'A elección del cliente',
            'is_active' => true,
        ]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('product.variants.0.is_custom_color', true)
            );
    }

    public function test_show_expone_solo_addons_activos_con_precio_efectivo_del_pivote(): void
    {
        $product = $this->producto();

        $conOverride = Addon::factory()->conTexto()->create(['price' => 900, 'max_characters' => 30]);
        $sinOverride = Addon::factory()->create(['price' => 400]);
        $inactivo = Addon::factory()->inactive()->create(['price' => 100]);

        $product->addons()->attach($conOverride->id, ['price_override' => 150, 'sort_order' => 0]);
        $product->addons()->attach($sinOverride->id, ['price_override' => null, 'sort_order' => 1]);
        $product->addons()->attach($inactivo->id, ['price_override' => null, 'sort_order' => 2]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('product.addons', 2)
                ->where('product.addons.0.id', $conOverride->id)
                ->where('product.addons.0.price', 900)
                ->where('product.addons.0.price_override', 150)
                ->where('product.addons.0.requires_text', true)
                ->where('product.addons.0.max_characters', 30)
                ->where('product.addons.0.text_placeholder', 'Nombre a grabar')
                ->where('product.addons.1.id', $sinOverride->id)
                ->where('product.addons.1.requires_text', false)
                ->whereNull('product.addons.1.price_override')
            );
    }

    public function test_show_incluye_product_variant_id_en_cada_imagen(): void
    {
        $product = $this->producto();
        $variante = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Rojo',
        ]);

        ProductImage::create([
            'product_id' => $product->id,
            'product_variant_id' => $variante->id,
            'path' => 'rojo.jpg',
            'sort_order' => 1,
            'type' => 'image',
        ]);
        ProductImage::create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'path' => 'general.jpg',
            'sort_order' => 2,
            'type' => 'image',
        ]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('product.images.0.product_variant_id', $variante->id)
                ->whereNull('product.images.1.product_variant_id')
            );
    }

    public function test_show_producto_sin_variantes_ni_addons_manda_arrays_vacios(): void
    {
        $product = $this->producto();

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Products/Show')
                ->has('product.variants', 0)
                ->has('product.addons', 0)
            );
    }

    public function test_el_precio_de_entrada_no_incluye_el_recargo_de_la_variante(): void
    {
        $product = $this->producto(['price' => 100]);
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Con recargo',
            'price_addon' => 500,
            'is_active' => true,
        ]);

        $esperado = app(PricingService::class)->calcularPrecio($product->fresh(), 1);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                // La vidriera muestra el precio base antes de elegir color: el
                // recargo de variante lo suma el mirror JS en vivo, no el backend.
                ->where('product.pricing.final_price', (int) $esperado->precioUnitarioFinal)
                ->where('product.price', 100)
            );
    }
}
