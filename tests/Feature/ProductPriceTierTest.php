<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductPriceTier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductPriceTierTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_has_many_price_tiers_ordered_by_cantidad_minima(): void
    {
        $product = Product::factory()->create();

        $tierAlto = ProductPriceTier::factory()->create([
            'product_id' => $product->id,
            'cantidad_minima' => 10,
            'precio_unitario' => 80,
        ]);
        $tierBajo = ProductPriceTier::factory()->create([
            'product_id' => $product->id,
            'cantidad_minima' => 5,
            'precio_unitario' => 90,
        ]);

        $tiers = $product->priceTiers;

        $this->assertCount(2, $tiers);
        $this->assertTrue($tiers->first()->is($tierBajo));
        $this->assertTrue($tiers->last()->is($tierAlto));
    }

    public function test_product_offer_belongs_to_price_tier(): void
    {
        $product = Product::factory()->create();
        $tier = ProductPriceTier::factory()->create(['product_id' => $product->id]);

        $offer = $product->offers()->create([
            'offer_price' => 100,
            'is_active' => true,
            'product_price_tier_id' => $tier->id,
        ]);

        $this->assertInstanceOf(ProductPriceTier::class, $offer->priceTier);
        $this->assertTrue($offer->priceTier->is($tier));
    }

    public function test_product_offer_price_tier_is_nullable(): void
    {
        $product = Product::factory()->create();

        $offer = $product->offers()->create([
            'offer_price' => 100,
            'is_active' => true,
        ]);

        $this->assertNull($offer->product_price_tier_id);
        $this->assertNull($offer->priceTier);
    }

    public function test_tier_aplicable_returns_tier_at_exact_threshold(): void
    {
        $product = Product::factory()->create();
        ProductPriceTier::factory()->create(['product_id' => $product->id, 'cantidad_minima' => 5, 'precio_unitario' => 90]);
        ProductPriceTier::factory()->create(['product_id' => $product->id, 'cantidad_minima' => 10, 'precio_unitario' => 80]);

        $tier = $product->tierAplicable(5);

        $this->assertNotNull($tier);
        $this->assertSame(5, $tier->cantidad_minima);
    }

    public function test_tier_aplicable_returns_highest_qualifying_tier_between_thresholds(): void
    {
        $product = Product::factory()->create();
        ProductPriceTier::factory()->create(['product_id' => $product->id, 'cantidad_minima' => 5, 'precio_unitario' => 90]);
        ProductPriceTier::factory()->create(['product_id' => $product->id, 'cantidad_minima' => 10, 'precio_unitario' => 80]);

        $tier = $product->tierAplicable(7);

        $this->assertNotNull($tier);
        $this->assertSame(5, $tier->cantidad_minima);
    }

    public function test_tier_aplicable_returns_null_below_first_threshold(): void
    {
        $product = Product::factory()->create();
        ProductPriceTier::factory()->create(['product_id' => $product->id, 'cantidad_minima' => 5, 'precio_unitario' => 90]);
        ProductPriceTier::factory()->create(['product_id' => $product->id, 'cantidad_minima' => 10, 'precio_unitario' => 80]);

        $this->assertNull($product->tierAplicable(3));
    }

    // --- N+1 fix (Fase C5): tierAplicable() debe reusar priceTiers si ya viene
    // eager-loaded, en vez de ignorar el eager-load y pegar una query propia. ------

    public function test_tier_aplicable_no_dispara_query_cuando_price_tiers_viene_eager_loaded(): void
    {
        $product = Product::factory()->create();
        ProductPriceTier::factory()->create(['product_id' => $product->id, 'cantidad_minima' => 5, 'precio_unitario' => 90]);
        ProductPriceTier::factory()->create(['product_id' => $product->id, 'cantidad_minima' => 10, 'precio_unitario' => 80]);

        $product = Product::with('priceTiers')->find($product->id);

        $tierQueries = 0;
        DB::listen(function ($query) use (&$tierQueries) {
            if (str_contains($query->sql, 'from "product_price_tiers"')) {
                $tierQueries++;
            }
        });

        $tier = $product->tierAplicable(7);

        $this->assertNotNull($tier);
        $this->assertSame(5, $tier->cantidad_minima);
        $this->assertSame(0, $tierQueries);
    }

    public function test_tier_aplicable_sigue_resolviendo_por_query_directa_si_no_viene_eager_loaded(): void
    {
        $product = Product::factory()->create();
        ProductPriceTier::factory()->create(['product_id' => $product->id, 'cantidad_minima' => 5, 'precio_unitario' => 90]);
        ProductPriceTier::factory()->create(['product_id' => $product->id, 'cantidad_minima' => 10, 'precio_unitario' => 80]);

        // Sin with('priceTiers'): esto es lo que hace, por ejemplo, el carrito hoy.
        $product = Product::find($product->id);

        $tierQueries = 0;
        DB::listen(function ($query) use (&$tierQueries) {
            if (str_contains($query->sql, 'from "product_price_tiers"')) {
                $tierQueries++;
            }
        });

        $tier = $product->tierAplicable(7);

        $this->assertNotNull($tier);
        $this->assertSame(5, $tier->cantidad_minima);
        $this->assertSame(1, $tierQueries);
    }

    public function test_tier_aplicable_eager_loaded_y_query_directa_devuelven_el_mismo_resultado(): void
    {
        $product = Product::factory()->create();
        ProductPriceTier::factory()->create(['product_id' => $product->id, 'cantidad_minima' => 5, 'precio_unitario' => 90]);
        ProductPriceTier::factory()->create(['product_id' => $product->id, 'cantidad_minima' => 10, 'precio_unitario' => 80]);

        $viaQuery = Product::find($product->id)->tierAplicable(10);
        $viaMemoria = Product::with('priceTiers')->find($product->id)->tierAplicable(10);

        $this->assertNotNull($viaQuery);
        $this->assertNotNull($viaMemoria);
        $this->assertSame($viaQuery->id, $viaMemoria->id);
        $this->assertSame(10, $viaMemoria->cantidad_minima);
    }
}
