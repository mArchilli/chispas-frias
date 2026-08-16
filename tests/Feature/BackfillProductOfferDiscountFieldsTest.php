<?php

namespace Tests\Feature;

use App\Enums\AlcanceOferta;
use App\Enums\TipoDescuento;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillProductOfferDiscountFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_converts_offer_price_into_a_fixed_discount_amount(): void
    {
        $product = Product::factory()->create(['price' => 1000]);
        $offer = $product->offers()->create([
            'offer_price' => 800,
            'is_active' => true,
        ]);

        $this->artisan('offers:backfill-discount-fields')
            ->expectsOutputToContain('Ofertas migradas: 1')
            ->assertExitCode(0);

        $offer->refresh();

        $this->assertSame(TipoDescuento::Fijo, $offer->tipo_descuento);
        $this->assertEquals(200.00, $offer->valor_descuento);
        $this->assertSame(AlcanceOferta::Todos, $offer->alcance);
        $this->assertNull($offer->product_price_tier_id);
    }

    public function test_backfill_is_idempotent_and_skips_already_migrated_offers(): void
    {
        $product = Product::factory()->create(['price' => 1000]);
        $product->offers()->create(['offer_price' => 800, 'is_active' => true]);

        $this->artisan('offers:backfill-discount-fields')->assertExitCode(0);

        $this->artisan('offers:backfill-discount-fields')
            ->expectsOutputToContain('Ofertas migradas: 0')
            ->assertExitCode(0);
    }

    public function test_backfill_clamps_negative_discount_to_zero(): void
    {
        $product = Product::factory()->create(['price' => 1000]);
        $offer = $product->offers()->create([
            'offer_price' => 1200,
            'is_active' => true,
        ]);

        $this->artisan('offers:backfill-discount-fields')->assertExitCode(0);

        $offer->refresh();

        $this->assertEquals(0.00, $offer->valor_descuento);
    }
}
