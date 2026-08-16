<?php

namespace Tests\Feature;

use App\Enums\AlcanceOferta;
use App\Enums\TipoDescuento;
use App\Models\Product;
use App\Models\ProductPriceTier;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PricingServiceTest extends TestCase
{
    use RefreshDatabase;

    private PricingService $pricingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pricingService = app(PricingService::class);
    }

    // --- sin tiers, sin oferta ------------------------------------------------------

    public function test_sin_tiers_ni_oferta_usa_el_precio_base(): void
    {
        $product = Product::factory()->create(['price' => 100]);

        $result = $this->pricingService->calcularPrecio($product, 1);

        $this->assertSame(100.0, $result->precioLista);
        $this->assertSame(100.0, $result->precioUnitarioFinal);
        $this->assertNull($result->tierAplicado);
        $this->assertNull($result->ofertaAplicada);
        $this->assertSame(0.0, $result->ahorroUnitario);
        $this->assertSame(0.0, $result->ahorroPorcentaje);
    }

    // --- tiers -------------------------------------------------------------------

    public function test_con_tier_sin_oferta_cantidad_que_cae_en_el_tier_usa_ese_precio(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        $tier = ProductPriceTier::factory()->create([
            'product_id' => $product->id,
            'cantidad_minima' => 10,
            'precio_unitario' => 80,
        ]);

        $result = $this->pricingService->calcularPrecio($product, 10);

        $this->assertSame(80.0, $result->precioLista);
        $this->assertSame(80.0, $result->precioUnitarioFinal);
        $this->assertTrue($result->tierAplicado->is($tier));
    }

    public function test_con_tiers_cantidad_menor_al_primer_umbral_usa_el_precio_base(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        ProductPriceTier::factory()->create([
            'product_id' => $product->id,
            'cantidad_minima' => 10,
            'precio_unitario' => 80,
        ]);

        $result = $this->pricingService->calcularPrecio($product, 5);

        $this->assertSame(100.0, $result->precioLista);
        $this->assertNull($result->tierAplicado);
    }

    public function test_cantidad_exactamente_igual_a_cantidad_minima_toma_ese_tier(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        $tierBajo = ProductPriceTier::factory()->create([
            'product_id' => $product->id,
            'cantidad_minima' => 5,
            'precio_unitario' => 90,
        ]);
        $tierAlto = ProductPriceTier::factory()->create([
            'product_id' => $product->id,
            'cantidad_minima' => 10,
            'precio_unitario' => 80,
        ]);

        $result = $this->pricingService->calcularPrecio($product, 10);

        $this->assertTrue($result->tierAplicado->is($tierAlto));
        $this->assertSame(80.0, $result->precioLista);
    }

    public function test_cantidad_entre_dos_umbrales_toma_el_menor_de_los_dos_que_cumple(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        $tierBajo = ProductPriceTier::factory()->create([
            'product_id' => $product->id,
            'cantidad_minima' => 5,
            'precio_unitario' => 90,
        ]);
        ProductPriceTier::factory()->create([
            'product_id' => $product->id,
            'cantidad_minima' => 10,
            'precio_unitario' => 80,
        ]);

        $result = $this->pricingService->calcularPrecio($product, 7);

        $this->assertTrue($result->tierAplicado->is($tierBajo));
        $this->assertSame(90.0, $result->precioLista);
    }

    // --- ofertas sin tier ----------------------------------------------------------

    public function test_sin_tier_con_oferta_alcance_todos_tipo_porcentaje(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        $offer = $product->offers()->create([
            'offer_price' => 75,
            'is_active' => true,
            'tipo_descuento' => TipoDescuento::Porcentaje,
            'valor_descuento' => 25,
            'alcance' => AlcanceOferta::Todos,
        ]);

        $result = $this->pricingService->calcularPrecio($product, 1);

        $this->assertSame(100.0, $result->precioLista);
        $this->assertSame(75.0, $result->precioUnitarioFinal);
        $this->assertTrue($result->ofertaAplicada->is($offer));
        $this->assertSame(25.0, $result->ahorroUnitario);
        $this->assertSame(25.0, $result->ahorroPorcentaje);
    }

    public function test_sin_tier_con_oferta_alcance_todos_tipo_fijo(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        $offer = $product->offers()->create([
            'offer_price' => 70,
            'is_active' => true,
            'tipo_descuento' => TipoDescuento::Fijo,
            'valor_descuento' => 30,
            'alcance' => AlcanceOferta::Todos,
        ]);

        $result = $this->pricingService->calcularPrecio($product, 1);

        $this->assertSame(100.0, $result->precioLista);
        $this->assertSame(70.0, $result->precioUnitarioFinal);
        $this->assertTrue($result->ofertaAplicada->is($offer));
        $this->assertSame(30.0, $result->ahorroUnitario);
        $this->assertSame(30.0, $result->ahorroPorcentaje);
    }

    // --- combinación tier + oferta ---------------------------------------------------

    public function test_con_tier_y_oferta_alcance_todos_el_descuento_se_aplica_sobre_el_precio_del_tier(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        ProductPriceTier::factory()->create([
            'product_id' => $product->id,
            'cantidad_minima' => 10,
            'precio_unitario' => 80,
        ]);
        $product->offers()->create([
            'offer_price' => 60,
            'is_active' => true,
            'tipo_descuento' => TipoDescuento::Porcentaje,
            'valor_descuento' => 25,
            'alcance' => AlcanceOferta::Todos,
        ]);

        $result = $this->pricingService->calcularPrecio($product, 10);

        // 25% sobre 80 (precio del tier), no sobre 100 (precio base).
        $this->assertSame(80.0, $result->precioLista);
        $this->assertSame(60.0, $result->precioUnitarioFinal);
    }

    public function test_con_tier_y_oferta_especifica_apuntando_a_ese_mismo_tier_se_aplica(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        $tier = ProductPriceTier::factory()->create([
            'product_id' => $product->id,
            'cantidad_minima' => 10,
            'precio_unitario' => 80,
        ]);
        $product->offers()->create([
            'offer_price' => 60,
            'is_active' => true,
            'tipo_descuento' => TipoDescuento::Fijo,
            'valor_descuento' => 20,
            'alcance' => AlcanceOferta::Especifico,
            'product_price_tier_id' => $tier->id,
        ]);

        $result = $this->pricingService->calcularPrecio($product, 10);

        $this->assertNotNull($result->ofertaAplicada);
        $this->assertSame(60.0, $result->precioUnitarioFinal);
    }

    public function test_con_tier_y_oferta_especifica_apuntando_a_otro_tier_no_se_aplica(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        $tierUsado = ProductPriceTier::factory()->create([
            'product_id' => $product->id,
            'cantidad_minima' => 10,
            'precio_unitario' => 80,
        ]);
        $otroTier = ProductPriceTier::factory()->create([
            'product_id' => $product->id,
            'cantidad_minima' => 20,
            'precio_unitario' => 60,
        ]);
        $product->offers()->create([
            'offer_price' => 40,
            'is_active' => true,
            'tipo_descuento' => TipoDescuento::Fijo,
            'valor_descuento' => 20,
            'alcance' => AlcanceOferta::Especifico,
            'product_price_tier_id' => $otroTier->id,
        ]);

        $result = $this->pricingService->calcularPrecio($product, 10);

        $this->assertNull($result->ofertaAplicada);
        $this->assertSame(80.0, $result->precioUnitarioFinal);
    }

    public function test_con_tier_y_oferta_especifica_apuntando_al_precio_base_no_se_aplica(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        ProductPriceTier::factory()->create([
            'product_id' => $product->id,
            'cantidad_minima' => 10,
            'precio_unitario' => 80,
        ]);
        $product->offers()->create([
            'offer_price' => 90,
            'is_active' => true,
            'tipo_descuento' => TipoDescuento::Fijo,
            'valor_descuento' => 10,
            'alcance' => AlcanceOferta::Especifico,
            'product_price_tier_id' => null,
        ]);

        $result = $this->pricingService->calcularPrecio($product, 10);

        $this->assertNull($result->ofertaAplicada);
        $this->assertSame(80.0, $result->precioUnitarioFinal);
    }

    public function test_oferta_especifica_al_precio_base_se_aplica_cuando_la_cantidad_no_resuelve_tier(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        ProductPriceTier::factory()->create([
            'product_id' => $product->id,
            'cantidad_minima' => 10,
            'precio_unitario' => 80,
        ]);
        $product->offers()->create([
            'offer_price' => 90,
            'is_active' => true,
            'tipo_descuento' => TipoDescuento::Fijo,
            'valor_descuento' => 10,
            'alcance' => AlcanceOferta::Especifico,
            'product_price_tier_id' => null,
        ]);

        $result = $this->pricingService->calcularPrecio($product, 1);

        $this->assertNotNull($result->ofertaAplicada);
        $this->assertSame(90.0, $result->precioUnitarioFinal);
    }

    // --- ofertas que no deben aplicar -----------------------------------------------

    public function test_oferta_vencida_nunca_se_aplica(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        $product->offers()->create([
            'offer_price' => 50,
            'is_active' => true,
            'tipo_descuento' => TipoDescuento::Porcentaje,
            'valor_descuento' => 50,
            'alcance' => AlcanceOferta::Todos,
            'start_date' => now()->subDays(10),
            'end_date' => now()->subDay(),
        ]);

        $result = $this->pricingService->calcularPrecio($product, 1);

        $this->assertNull($result->ofertaAplicada);
        $this->assertSame(100.0, $result->precioUnitarioFinal);
    }

    public function test_oferta_inactiva_nunca_se_aplica(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        $product->offers()->create([
            'offer_price' => 50,
            'is_active' => false,
            'tipo_descuento' => TipoDescuento::Porcentaje,
            'valor_descuento' => 50,
            'alcance' => AlcanceOferta::Todos,
        ]);

        $result = $this->pricingService->calcularPrecio($product, 1);

        $this->assertNull($result->ofertaAplicada);
        $this->assertSame(100.0, $result->precioUnitarioFinal);
    }

    // --- clamping ------------------------------------------------------------------

    public function test_descuento_fijo_mayor_al_precio_se_clampea_a_cero(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        $product->offers()->create([
            'offer_price' => 0,
            'is_active' => true,
            'tipo_descuento' => TipoDescuento::Fijo,
            'valor_descuento' => 500,
            'alcance' => AlcanceOferta::Todos,
        ]);

        $result = $this->pricingService->calcularPrecio($product, 1);

        $this->assertSame(0.0, $result->precioUnitarioFinal);
        $this->assertSame(100.0, $result->ahorroUnitario);
        $this->assertSame(100.0, $result->ahorroPorcentaje);
    }

    // --- performance / N+1 ----------------------------------------------------------

    public function test_calcular_precio_ejecuta_como_maximo_una_query_a_product_offers(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        $product->offers()->create([
            'offer_price' => 75,
            'is_active' => true,
            'tipo_descuento' => TipoDescuento::Porcentaje,
            'valor_descuento' => 25,
            'alcance' => AlcanceOferta::Todos,
        ]);

        // Producto "fresco", sin relaciones eager-loaded, como llegaría desde un
        // Product::find() suelto.
        $product = Product::find($product->id);

        $offerQueries = 0;
        DB::listen(function ($query) use (&$offerQueries) {
            if (str_contains($query->sql, 'from "product_offers"')) {
                $offerQueries++;
            }
        });

        $result = $this->pricingService->calcularPrecio($product, 1);

        $this->assertSame(75.0, $result->precioUnitarioFinal);
        $this->assertSame(1, $offerQueries);
    }

    public function test_calcular_precio_no_repite_query_a_product_offers_en_llamadas_sucesivas(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        $product->offers()->create([
            'offer_price' => 75,
            'is_active' => true,
            'tipo_descuento' => TipoDescuento::Porcentaje,
            'valor_descuento' => 25,
            'alcance' => AlcanceOferta::Todos,
        ]);
        $product = Product::find($product->id);

        $offerQueries = 0;
        DB::listen(function ($query) use (&$offerQueries) {
            if (str_contains($query->sql, 'from "product_offers"')) {
                $offerQueries++;
            }
        });

        $this->pricingService->calcularPrecio($product, 1);
        $this->pricingService->calcularPrecio($product, 5);
        $this->pricingService->calcularPrecio($product, 10);

        $this->assertSame(1, $offerQueries);
    }

    public function test_calcular_precio_no_dispara_query_a_product_offers_si_ya_viene_eager_loaded(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        $product->offers()->create([
            'offer_price' => 75,
            'is_active' => true,
            'tipo_descuento' => TipoDescuento::Porcentaje,
            'valor_descuento' => 25,
            'alcance' => AlcanceOferta::Todos,
        ]);

        $product = Product::with('currentOffer')->find($product->id);

        $offerQueries = 0;
        DB::listen(function ($query) use (&$offerQueries) {
            if (str_contains($query->sql, 'from "product_offers"')) {
                $offerQueries++;
            }
        });

        $result = $this->pricingService->calcularPrecio($product, 1);

        $this->assertSame(75.0, $result->precioUnitarioFinal);
        $this->assertSame(0, $offerQueries);
    }
}
