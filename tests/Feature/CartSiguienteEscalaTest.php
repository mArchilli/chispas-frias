<?php

namespace Tests\Feature;

use App\Enums\AlcanceOferta;
use App\Enums\TipoDescuento;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPriceTier;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CHK-2c — «Sumá N más y pagás $X c/u»: el carrito (página y drawer) informa el próximo escalón de precio por cantidad cuando baja
 * el precio. Se calcula con PricingService en la cantidad del escalón, así que incluye la oferta y las opciones de la línea. El
 * escalón se resuelve por producto (suma de sus líneas, ver CartEscalasPorProductoTest); acá, con una sola línea por producto, es su cantidad.
 */
class CartSiguienteEscalaTest extends TestCase
{
    use RefreshDatabase;

    private function producto(array $overrides = []): Product
    {
        return Product::factory()->for(Category::factory()->create())->create(array_merge([
            'price' => 100,
            'stock' => 100,
            'is_active' => true,
        ], $overrides));
    }

    private function escala(Product $producto, int $desde, float $precio): ProductPriceTier
    {
        return ProductPriceTier::factory()->create(['product_id' => $producto->id, 'cantidad_minima' => $desde, 'precio_unitario' => $precio]);
    }

    private function linea(Product $producto, int $cantidad, ?ProductVariant $variante = null): array
    {
        return [
            'line_key' => "p{$producto->id}:v".($variante?->id ?? 0),
            'product_id' => $producto->id,
            'quantity' => $cantidad,
            'variant_id' => $variante?->id,
            'addon_selections' => [],
            'custom_color_text' => null,
        ];
    }

    private function siguiente(Product $producto, int $cantidad, ?ProductVariant $variante = null): ?array
    {
        return $this->withSession(['cart' => [$this->linea($producto, $cantidad, $variante)]])
            ->get(route('cart.index'))
            ->viewData('page')['props']['cartItems'][0]['siguiente_escala'];
    }

    public function test_informa_cuantas_unidades_faltan_para_el_proximo_escalon_y_su_precio(): void
    {
        $producto = $this->producto();
        $this->escala($producto, 5, 90);
        $this->escala($producto, 10, 80);

        $this->assertEquals(['faltan' => 3, 'precio_unitario' => 90.0], $this->siguiente($producto, 2));
        $this->assertEquals(['faltan' => 5, 'precio_unitario' => 80.0], $this->siguiente($producto, 5));
    }

    public function test_en_el_ultimo_escalon_o_sin_escalas_no_hay_aviso(): void
    {
        $producto = $this->producto();
        $this->escala($producto, 5, 90);
        $sinEscalas = $this->producto();

        $this->assertNull($this->siguiente($producto, 5));
        $this->assertNull($this->siguiente($producto, 12));
        $this->assertNull($this->siguiente($sinEscalas, 3));
    }

    public function test_incluye_la_oferta_vigente_en_el_precio_del_escalon(): void
    {
        $producto = $this->producto();
        $this->escala($producto, 5, 90);
        $producto->offers()->create([
            'offer_price' => 0,
            'is_active' => true,
            'tipo_descuento' => TipoDescuento::Porcentaje,
            'valor_descuento' => 10,
            'alcance' => AlcanceOferta::Todos,
        ]);

        // 90 con el 10 % de oferta = 81.
        $this->assertEquals(['faltan' => 4, 'precio_unitario' => 81.0], $this->siguiente($producto, 1));
    }

    public function test_incluye_el_recargo_del_color_y_se_compara_con_el_unitario_real(): void
    {
        $producto = $this->producto();
        $this->escala($producto, 5, 90);
        $roja = ProductVariant::factory()->create(['product_id' => $producto->id, 'price_addon' => 30, 'stock' => 50]);

        // Actual: 100 + 30 = 130; en el escalón: 90 + 30 = 120.
        $this->assertEquals(['faltan' => 4, 'precio_unitario' => 120.0], $this->siguiente($producto, 1, $roja));
    }

    public function test_si_el_escalon_no_baja_el_precio_no_se_avisa(): void
    {
        $producto = $this->producto();
        $this->escala($producto, 5, 90);
        // Oferta acotada al precio base: con 1 unidad deja 100 - 10 = 90, pero en el escalón de 5 ya no aplica y vale 90 de
        // lista. Sumar unidades no baja el precio, así que no hay nada que avisar.
        $producto->offers()->create([
            'offer_price' => 90,
            'is_active' => true,
            'tipo_descuento' => TipoDescuento::Fijo,
            'valor_descuento' => 10,
            'alcance' => AlcanceOferta::Especifico,
            'product_price_tier_id' => null,
        ]);

        $this->assertNull($this->siguiente($producto, 1));
    }

    public function test_el_drawer_trae_el_mismo_aviso(): void
    {
        $producto = $this->producto();
        $this->escala($producto, 5, 90);

        $this->withSession(['cart' => [$this->linea($producto, 3)]])
            ->getJson(route('cart.preview'))
            ->assertOk()
            ->assertJsonPath('cartItems.0.siguiente_escala.faltan', 2)
            ->assertJsonPath('cartItems.0.siguiente_escala.precio_unitario', 90);
    }
}
