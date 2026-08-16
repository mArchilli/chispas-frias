<?php

namespace Tests\Feature;

use App\Enums\AlcanceOferta;
use App\Enums\TipoDescuento;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPriceTier;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Fase C5: el catálogo público (ProductController@index/@show) reemplaza los
 * $appends legacy de Product (current_price, discount_percentage, etc.) por
 * precios resueltos con PricingService. Estos tests confirman que los props que
 * llegan a React coinciden con lo que devuelve el servicio — sin hardcodear los
 * montos esperados — y que el fix del N+1 de tierAplicable() sostiene el
 * catálogo completo sin que las queries a product_price_tiers escalen con la
 * cantidad de productos.
 */
class PublicCatalogPricingTest extends TestCase
{
    use RefreshDatabase;

    private PricingService $pricingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pricingService = app(PricingService::class);
    }

    /**
     * AssertableInertia compara los props tras un round-trip json_encode/json_decode
     * (ver AssertableInertia::fromTestResponse), que colapsa floats "enteros" (90.0)
     * a int (90). Este helper normaliza el valor esperado de la misma forma para
     * poder comparar con assertSame sin hardcodear el número, solo su forma.
     */
    private function asInertiaNumber(float $value): int|float
    {
        return $value == (int) $value ? (int) $value : $value;
    }

    // --- index() --------------------------------------------------------------------

    public function test_index_sin_tiers_ni_oferta_usa_el_precio_de_entrada_de_pricing_service(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create([
            'price' => 150, 'stock' => 20, 'is_active' => true,
        ]);

        $esperado = $this->pricingService->calcularPrecio($product, 1);

        $response = $this->get(route('products.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Products/Index')
            ->where('products.data.0.pricing.list_price', $this->asInertiaNumber($esperado->precioLista))
            ->where('products.data.0.pricing.final_price', $this->asInertiaNumber($esperado->precioUnitarioFinal))
            ->where('products.data.0.pricing.has_discount', false)
            ->where('products.data.0.pricing.has_tiers', false)
        );
    }

    public function test_index_con_oferta_activa_alcance_todos_refleja_precio_final_de_pricing_service(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create([
            'price' => 200, 'stock' => 20, 'is_active' => true,
        ]);
        $product->offers()->create([
            'is_active' => true,
            'tipo_descuento' => TipoDescuento::Porcentaje,
            'valor_descuento' => 30,
            'alcance' => AlcanceOferta::Todos,
        ]);

        $esperado = $this->pricingService->calcularPrecio($product->fresh(), 1);

        $response = $this->get(route('products.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Products/Index')
            ->where('products.data.0.pricing.final_price', $this->asInertiaNumber($esperado->precioUnitarioFinal))
            ->where('products.data.0.pricing.has_discount', true)
            ->where('products.data.0.pricing.savings_percentage', $this->asInertiaNumber($esperado->ahorroPorcentaje))
        );
    }

    public function test_index_con_tiers_indica_has_tiers_y_el_ahorro_maximo_calculado_con_pricing_service(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create([
            'price' => 100, 'stock' => 50, 'is_active' => true,
        ]);
        ProductPriceTier::factory()->create(['product_id' => $product->id, 'cantidad_minima' => 5, 'precio_unitario' => 90]);
        $mejorTier = ProductPriceTier::factory()->create(['product_id' => $product->id, 'cantidad_minima' => 10, 'precio_unitario' => 70]);

        $product = $product->fresh();
        $baseResult = $this->pricingService->calcularPrecio($product, 1);
        $mejorResult = $this->pricingService->calcularPrecio($product, $mejorTier->cantidad_minima);
        $ahorroEsperado = round((1 - $mejorResult->precioUnitarioFinal / $baseResult->precioUnitarioFinal) * 100, 2);

        $response = $this->get(route('products.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Products/Index')
            ->where('products.data.0.pricing.has_tiers', true)
            ->where('products.data.0.pricing.max_tier_savings_percentage', $this->asInertiaNumber($ahorroEsperado))
        );
    }

    public function test_index_sin_tiers_max_tier_savings_percentage_es_null(): void
    {
        $category = Category::factory()->create();
        Product::factory()->for($category)->create(['is_active' => true, 'stock' => 20]);

        $response = $this->get(route('products.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Products/Index')
            ->whereNull('products.data.0.pricing.max_tier_savings_percentage')
        );
    }

    // --- show() -------------------------------------------------------------------

    public function test_show_devuelve_price_tiers_completos_y_el_precio_de_entrada_de_pricing_service(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create([
            'price' => 100, 'stock' => 50, 'is_active' => true,
        ]);
        ProductPriceTier::factory()->create(['product_id' => $product->id, 'cantidad_minima' => 5, 'precio_unitario' => 90]);
        ProductPriceTier::factory()->create(['product_id' => $product->id, 'cantidad_minima' => 10, 'precio_unitario' => 80]);

        $esperado = $this->pricingService->calcularPrecio($product->fresh(), 1);

        $response = $this->get(route('products.show', $product));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Products/Show')
            ->where('product.pricing.final_price', $this->asInertiaNumber($esperado->precioUnitarioFinal))
            ->where('product.price_tiers.0.cantidad_minima', 5)
            ->where('product.price_tiers.0.precio_unitario', 90)
            ->where('product.price_tiers.1.cantidad_minima', 10)
            ->where('product.price_tiers.1.precio_unitario', 80)
        );
    }

    public function test_show_con_oferta_especifica_sobre_un_tier_el_precio_de_entrada_no_se_ve_afectado(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create([
            'price' => 100, 'stock' => 50, 'is_active' => true,
        ]);
        $tier = ProductPriceTier::factory()->create(['product_id' => $product->id, 'cantidad_minima' => 10, 'precio_unitario' => 80]);
        $product->offers()->create([
            'is_active' => true,
            'tipo_descuento' => TipoDescuento::Fijo,
            'valor_descuento' => 20,
            'alcance' => AlcanceOferta::Especifico,
            'product_price_tier_id' => $tier->id,
        ]);

        $product = $product->fresh();
        $esperadoBase = $this->pricingService->calcularPrecio($product, 1);
        $esperadoEnTier = $this->pricingService->calcularPrecio($product, $tier->cantidad_minima);

        // La oferta apunta al tier, no al precio base: a cantidad=1 no debe aplicarse.
        $this->assertNull($esperadoBase->ofertaAplicada);
        $this->assertNotNull($esperadoEnTier->ofertaAplicada);

        $response = $this->get(route('products.show', $product));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Products/Show')
            ->where('product.pricing.final_price', $this->asInertiaNumber($esperadoBase->precioUnitarioFinal))
            ->where('product.pricing.has_discount', false)
            ->where('product.current_offer.alcance', 'especifico')
            ->where('product.current_offer.product_price_tier_id', $tier->id)
            ->where('product.price_tiers.0.precio_unitario', 80)
        );
    }

    public function test_show_no_muestra_producto_inactivo(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create(['is_active' => false]);

        $this->get(route('products.show', $product))->assertNotFound();
    }

    public function test_show_productos_relacionados_usan_pricing_service_en_vez_de_appends_legacy(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create(['is_active' => true, 'stock' => 20]);
        $related = Product::factory()->for($category)->create([
            'is_active' => true, 'stock' => 20, 'price' => 300,
        ]);
        $related->offers()->create([
            'is_active' => true,
            'tipo_descuento' => TipoDescuento::Porcentaje,
            'valor_descuento' => 10,
            'alcance' => AlcanceOferta::Todos,
        ]);
        // Padding para garantizar exactamente 3 relacionados desde la misma categoría.
        Product::factory()->for($category)->count(2)->create(['is_active' => true, 'stock' => 20]);

        $esperado = $this->pricingService->calcularPrecio($related->fresh(), 1);

        $response = $this->get(route('products.show', $product));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Products/Show')
            ->has('relatedProducts', 3)
            ->where('relatedProducts', function ($relatedProducts) use ($related, $esperado) {
                $match = collect($relatedProducts)->firstWhere('id', $related->id);

                $this->assertNotNull($match, 'El producto con oferta no aparece entre los relacionados.');
                $this->assertSame(
                    $this->asInertiaNumber($esperado->precioUnitarioFinal),
                    $match['pricing']['final_price']
                );
                $this->assertTrue($match['pricing']['has_discount']);
                $this->assertArrayNotHasKey('current_offer', $match);
                $this->assertArrayNotHasKey('discount_percentage', $match);

                return true;
            })
        );
    }

    // --- performance: catálogo completo no escala en queries a product_price_tiers --

    public function test_index_no_escala_las_queries_a_product_price_tiers_con_la_cantidad_de_productos(): void
    {
        $category = Category::factory()->create();
        $productos = Product::factory()->for($category)->count(5)->create(['is_active' => true, 'stock' => 20]);

        foreach ($productos as $producto) {
            ProductPriceTier::factory()->create(['product_id' => $producto->id, 'cantidad_minima' => 5, 'precio_unitario' => 90]);
            ProductPriceTier::factory()->create(['product_id' => $producto->id, 'cantidad_minima' => 10, 'precio_unitario' => 80]);
        }

        $tierQueries = 0;
        DB::listen(function ($query) use (&$tierQueries) {
            if (str_contains($query->sql, 'from "product_price_tiers"')) {
                $tierQueries++;
            }
        });

        $response = $this->get(route('products.index'));

        $response->assertOk();

        // Antes de la Fase C5, tierAplicable() ignoraba el eager-load y disparaba una
        // query propia por producto (aquí: 5 productos * 1 llamada a calcularPrecio
        // cada uno = 5 queries, y más si se suma max_tier_savings_percentage). Con el
        // fix, with('priceTiers') resuelve todo en un solo batch y tierAplicable() lo
        // reusa en memoria sin importar cuántas veces se llame por producto.
        $this->assertSame(1, $tierQueries);
    }

    public function test_index_con_un_solo_producto_y_con_cinco_dispara_la_misma_cantidad_de_queries_a_product_price_tiers(): void
    {
        $category = Category::factory()->create();

        $unProducto = Product::factory()->for($category)->create(['is_active' => true, 'stock' => 20]);
        ProductPriceTier::factory()->create(['product_id' => $unProducto->id, 'cantidad_minima' => 5, 'precio_unitario' => 90]);

        $tierQueries = 0;
        DB::listen(function ($query) use (&$tierQueries) {
            if (str_contains($query->sql, 'from "product_price_tiers"')) {
                $tierQueries++;
            }
        });

        $this->get(route('products.index'))->assertOk();
        $queriesConUnProducto = $tierQueries;
        $this->assertGreaterThan(0, $queriesConUnProducto);

        // Reseteamos y repetimos el catálogo con más productos (mismo listado, más filas).
        Product::factory()->for($category)->count(4)->create(['is_active' => true, 'stock' => 20])
            ->each(fn (Product $p) => ProductPriceTier::factory()->create(['product_id' => $p->id, 'cantidad_minima' => 5, 'precio_unitario' => 90]));

        $tierQueries = 0;
        $this->get(route('products.index'))->assertOk();

        $this->assertSame($queriesConUnProducto, $tierQueries);
    }
}
