<?php

namespace Tests\Feature;

use App\Enums\AlcanceOferta;
use App\Enums\TipoDescuento;
use App\Exceptions\VarianteRequeridaException;
use App\Models\Addon;
use App\Models\Product;
use App\Models\ProductPriceTier;
use App\Models\ProductVariant;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cobertura de la extensión de PricingService::calcularPrecio() para variantes de
 * color y add-ons de personalización.
 */
class PricingServiceOpcionesTest extends TestCase
{
    use RefreshDatabase;

    private PricingService $pricingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pricingService = app(PricingService::class);
    }

    // --- compatibilidad con los callers actuales ---------------------------------

    public function test_sin_pasar_opciones_el_resultado_no_cambia(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'price_addon' => 500]);

        $result = $this->pricingService->calcularPrecio($product, 1);

        $this->assertNull($result->varianteAplicada);
        $this->assertSame([], $result->addonsAplicados);
        $this->assertSame(0.0, $result->recargoVariante);
        $this->assertSame(0.0, $result->addonsTotal);
        $this->assertSame(100.0, $result->precioUnitarioFinal);
        $this->assertSame(100.0, $result->precioFinalConOpciones);
    }

    // --- recargo de variante -----------------------------------------------------

    public function test_variante_suma_su_recargo_al_precio_final(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        $variante = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price_addon' => 250,
        ]);

        $result = $this->pricingService->calcularPrecio($product, 1, varianteId: $variante->id);

        $this->assertTrue($result->varianteAplicada->is($variante));
        $this->assertSame(250.0, $result->recargoVariante);
        $this->assertSame(100.0, $result->precioUnitarioFinal);
        $this->assertSame(350.0, $result->precioFinalConOpciones);
    }

    // --- add-ons ---------------------------------------------------------------

    public function test_addons_suman_su_costo_usando_price_override_del_pivote(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        $addonBase = Addon::factory()->create(['price' => 300]);
        $addonOverride = Addon::factory()->create(['price' => 999]);

        $product->addons()->attach($addonBase->id, ['price_override' => null]);
        $product->addons()->attach($addonOverride->id, ['price_override' => 150]);

        $result = $this->pricingService->calcularPrecio(
            $product,
            1,
            addonIds: [$addonBase->id, $addonOverride->id],
        );

        $this->assertCount(2, $result->addonsAplicados);
        $this->assertSame(450.0, $result->addonsTotal); // 300 + 150 (override)
        $this->assertSame(550.0, $result->precioFinalConOpciones);
    }

    public function test_addon_ids_duplicados_se_cuentan_una_sola_vez(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        $addon = Addon::factory()->create(['price' => 200]);
        $product->addons()->attach($addon->id);

        $result = $this->pricingService->calcularPrecio(
            $product,
            1,
            addonIds: [$addon->id, $addon->id],
        );

        $this->assertCount(1, $result->addonsAplicados);
        $this->assertSame(200.0, $result->addonsTotal);
    }

    // --- la oferta nunca toca los recargos --------------------------------------

    public function test_la_oferta_descuenta_solo_el_precio_base_no_el_recargo_de_variante_ni_addons(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        $product->offers()->create([
            'offer_price' => 75,
            'is_active' => true,
            'tipo_descuento' => TipoDescuento::Porcentaje,
            'valor_descuento' => 25,
            'alcance' => AlcanceOferta::Todos,
        ]);
        $variante = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price_addon' => 200,
        ]);
        $addon = Addon::factory()->create(['price' => 100]);
        $product->addons()->attach($addon->id);

        $result = $this->pricingService->calcularPrecio(
            $product,
            1,
            varianteId: $variante->id,
            addonIds: [$addon->id],
        );

        // 25% solo sobre 100 => 75. Recargo (200) y add-on (100) intactos.
        $this->assertSame(75.0, $result->precioUnitarioFinal);
        $this->assertSame(200.0, $result->recargoVariante);
        $this->assertSame(100.0, $result->addonsTotal);
        $this->assertSame(375.0, $result->precioFinalConOpciones);
        $this->assertSame(25.0, $result->ahorroUnitario);
    }

    public function test_el_recargo_se_suma_sobre_el_precio_del_tier(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        ProductPriceTier::factory()->create([
            'product_id' => $product->id,
            'cantidad_minima' => 10,
            'precio_unitario' => 80,
        ]);
        $variante = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price_addon' => 30,
        ]);

        $result = $this->pricingService->calcularPrecio($product, 10, varianteId: $variante->id);

        $this->assertSame(80.0, $result->precioUnitarioFinal);
        $this->assertSame(110.0, $result->precioFinalConOpciones);
    }

    // --- validación de pertenencia / estado ------------------------------------

    public function test_variante_de_otro_producto_lanza_excepcion(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        $otroProducto = Product::factory()->create(['price' => 100]);
        $varianteAjena = ProductVariant::factory()->create(['product_id' => $otroProducto->id]);

        $this->expectException(VarianteRequeridaException::class);

        $this->pricingService->calcularPrecio($product, 1, varianteId: $varianteAjena->id);
    }

    public function test_variante_inactiva_lanza_excepcion(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        $variante = ProductVariant::factory()->inactive()->create(['product_id' => $product->id]);

        $this->expectException(VarianteRequeridaException::class);

        $this->pricingService->calcularPrecio($product, 1, varianteId: $variante->id);
    }

    public function test_addon_no_ofrecido_por_el_producto_lanza_excepcion(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        $addonSuelto = Addon::factory()->create();

        $this->expectException(VarianteRequeridaException::class);

        $this->pricingService->calcularPrecio($product, 1, addonIds: [$addonSuelto->id]);
    }

    public function test_addon_inactivo_lanza_excepcion(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        $addon = Addon::factory()->inactive()->create();
        $product->addons()->attach($addon->id);

        $this->expectException(VarianteRequeridaException::class);

        $this->pricingService->calcularPrecio($product, 1, addonIds: [$addon->id]);
    }

    // --- exigirVariante --------------------------------------------------------

    public function test_exigir_variante_sin_elegir_ninguna_lanza_excepcion_si_el_producto_tiene_variantes_activas(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        ProductVariant::factory()->create(['product_id' => $product->id]);

        $this->expectException(VarianteRequeridaException::class);

        $this->pricingService->calcularPrecio($product, 1, exigirVariante: true);
    }

    public function test_exigir_variante_no_lanza_si_el_producto_no_tiene_variantes_activas(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        ProductVariant::factory()->inactive()->create(['product_id' => $product->id]);

        $result = $this->pricingService->calcularPrecio($product, 1, exigirVariante: true);

        $this->assertNull($result->varianteAplicada);
        $this->assertSame(100.0, $result->precioFinalConOpciones);
    }

    public function test_precio_de_vidriera_no_exige_variante_aunque_el_producto_tenga_variantes(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'price_addon' => 500]);

        // exigirVariante: false (default) — la ficha pública muestra un precio
        // antes de que el cliente elija color.
        $result = $this->pricingService->calcularPrecio($product, 1);

        $this->assertNull($result->varianteAplicada);
        $this->assertSame(100.0, $result->precioFinalConOpciones);
    }

    public function test_exigir_variante_con_variante_valida_resuelve_normal(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        $variante = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price_addon' => 40,
        ]);

        $result = $this->pricingService->calcularPrecio(
            $product,
            1,
            varianteId: $variante->id,
            exigirVariante: true,
        );

        $this->assertTrue($result->varianteAplicada->is($variante));
        $this->assertSame(140.0, $result->precioFinalConOpciones);
    }
}
