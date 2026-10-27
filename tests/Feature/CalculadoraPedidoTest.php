<?php

namespace Tests\Feature;

use App\Exceptions\DiscountCodeInvalidoException;
use App\Exceptions\VarianteRequeridaException;
use App\Models\CardPaymentPlan;
use App\Models\Category;
use App\Models\Combo;
use App\Models\DiscountCode;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CalculadoraPedido;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * CalculadoraPedido es pura: calcula el pedido (ítems, cupón validado sin consumir y totales) y la
 * información de cuotas, sin escribir en la base ni en la sesión. El comportamiento del checkout
 * lo fija CheckoutCaracterizacionTest; acá se fija la propiedad de la pureza.
 */
class CalculadoraPedidoTest extends TestCase
{
    use RefreshDatabase;

    /** Corre $accion y devuelve las sentencias que NO son SELECT (escrituras o DDL). */
    private function escrituras(callable $accion): array
    {
        $escrituras = [];

        DB::listen(function (QueryExecuted $q) use (&$escrituras) {
            if (! preg_match('/^\s*select\b/i', $q->sql)) {
                $escrituras[] = $q->sql;
            }
        });

        $accion();

        return $escrituras;
    }

    private function producto(array $attrs = []): Product
    {
        return Product::factory()->for(Category::factory())->create(array_merge(['price' => 1000, 'stock' => 10], $attrs));
    }

    public function test_calcular_no_escribe_nada_ni_consume_el_cupon_ni_toca_el_stock(): void
    {
        $p = $this->producto();
        $codigo = DiscountCode::create(['code' => 'VERANO10', 'percentage' => 10, 'usage_limit' => 1, 'usage_count' => 0, 'is_active' => true]);
        $calculadora = app(CalculadoraPedido::class);
        $lineas = [['line_key' => 'k', 'product_id' => $p->id, 'quantity' => 4, 'variant_id' => null, 'addon_selections' => [], 'custom_color_text' => null]];

        $resultado = null;
        $escrituras = $this->escrituras(function () use ($calculadora, $lineas, &$resultado) {
            $calculo = $calculadora->calcularItems($lineas);
            $resultado = $calculadora->aplicarDescuento($calculo, 'verano10');
        });

        $this->assertSame([], $escrituras, 'La calculadora no puede escribir en la base.');

        $this->assertEquals(4000.0, $resultado->subtotal);
        $this->assertSame($codigo->id, $resultado->codigoDescuento->id);
        $this->assertSame(400.0, $resultado->descuento);
        $this->assertSame(3600.0, $resultado->total);
        $this->assertSame([['product_id' => $p->id, 'product_variant_id' => null, 'cantidad' => 4]], $resultado->lineasStock);

        // El cupón se validó pero NO se consumió, y el stock sigue intacto.
        $this->assertSame(0, $codigo->fresh()->usage_count);
        $this->assertSame(10, $p->fresh()->stock);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_no_toca_la_sesion(): void
    {
        $p = $this->producto();
        $lineas = [['line_key' => 'k', 'product_id' => $p->id, 'quantity' => 1, 'variant_id' => null, 'addon_selections' => [], 'custom_color_text' => null]];

        $this->withSession(['cart_discount_code' => 'NOEXISTE', 'cart_payment_plan' => ['id' => 999]]);

        $calculadora = app(CalculadoraPedido::class);
        $calculo = $calculadora->calcularItems($lineas);
        $final = $calculadora->aplicarDescuento($calculo, null);

        // La calculadora ignora lo que haya en la sesión (ni el cupón ni un plan viejo): eso lo decide el controller.
        $this->assertSame(1000.0, $final->total);
        $this->assertSame('NOEXISTE', session('cart_discount_code'));
        $this->assertSame(['id' => 999], session('cart_payment_plan'));
    }

    public function test_un_cupon_invalido_lanza_la_excepcion_sin_tocar_la_base(): void
    {
        $p = $this->producto();
        DiscountCode::create(['code' => 'VIEJO', 'percentage' => 10, 'usage_limit' => 1, 'usage_count' => 1, 'is_active' => true]);
        $calculadora = app(CalculadoraPedido::class);
        $calculo = $calculadora->calcularItems([['line_key' => 'k', 'product_id' => $p->id, 'quantity' => 1, 'variant_id' => null, 'addon_selections' => [], 'custom_color_text' => null]]);

        $this->expectException(DiscountCodeInvalidoException::class);

        $escrituras = $this->escrituras(fn () => $calculadora->aplicarDescuento($calculo, 'VIEJO'));
        $this->assertSame([], $escrituras);
    }

    public function test_con_exigir_variante_una_opcion_faltante_aborta_y_sin_exigirla_las_vistas_la_toleran(): void
    {
        $p = $this->producto();
        ProductVariant::factory()->create(['product_id' => $p->id, 'stock' => 5]);
        $calculadora = app(CalculadoraPedido::class);
        $sinColor = [['line_key' => 'k', 'product_id' => $p->id, 'quantity' => 1, 'variant_id' => null, 'addon_selections' => [], 'custom_color_text' => null]];
        $colorInexistente = [['line_key' => 'j', 'product_id' => $p->id, 'quantity' => 1, 'variant_id' => 999999, 'addon_selections' => [], 'custom_color_text' => null]];

        // Las vistas: sin color se muestra igual; con un color que ya no existe, la línea se descarta.
        $this->assertCount(1, $calculadora->calcularItems($sinColor, exigirVariante: false)->items);
        $this->assertTrue($calculadora->calcularItems($colorInexistente, exigirVariante: false)->items->isEmpty());

        // El checkout: cualquiera de las dos aborta.
        try {
            $calculadora->calcularItems($colorInexistente, exigirVariante: true);
            $this->fail('Tenía que lanzar VarianteRequeridaException.');
        } catch (VarianteRequeridaException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(VarianteRequeridaException::class);
        $calculadora->calcularItems($sinColor, exigirVariante: true);
    }

    public function test_expande_los_combos_a_unidades_de_stock_por_componente(): void
    {
        $a = $this->producto(['title' => 'A']);
        $b = $this->producto(['title' => 'B']);
        $combo = Combo::create(['title' => 'Combo', 'price' => 1800, 'is_free_shipping' => true, 'is_active' => true]);
        $combo->items()->create(['product_id' => $a->id, 'quantity' => 1, 'sort_order' => 0]);
        $combo->items()->create(['product_id' => $b->id, 'quantity' => 3, 'sort_order' => 1]);

        $calculo = app(CalculadoraPedido::class)->calcularItems([
            ['line_key' => 'c', 'combo_id' => $combo->id, 'quantity' => 2, 'component_selections' => []],
        ]);

        $this->assertTrue($calculo->envioGratisPorCombo);
        $this->assertSame(3600, (int) $calculo->subtotal);
        $this->assertSame([
            ['product_id' => $a->id, 'product_variant_id' => null, 'cantidad' => 2],
            ['product_id' => $b->id, 'product_variant_id' => null, 'cantidad' => 6],
        ], $calculo->lineasStock);
    }

    public function test_info_cuotas_calcula_sobre_el_total_con_los_planes_activos_de_menos_a_mas_cuotas(): void
    {
        CardPaymentPlan::factory()->create(['name' => '6 cuotas', 'installments' => 6, 'surcharge_percentage' => 30, 'sort_order' => 1]);
        CardPaymentPlan::factory()->create(['name' => '1 pago', 'installments' => 1, 'surcharge_percentage' => 0, 'sort_order' => 0]);
        CardPaymentPlan::factory()->inactive()->create(['name' => '12 cuotas', 'installments' => 12, 'surcharge_percentage' => 60]);

        $info = null;
        $escrituras = $this->escrituras(function () use (&$info) {
            $info = app(CalculadoraPedido::class)->infoCuotas(10000.0);
        });

        $this->assertSame([], $escrituras);
        $this->assertSame(10000.0, $info['efectivo']);
        $this->assertSame(6, $info['max_cuotas'], 'El plan inactivo no cuenta.');
        $this->assertTrue($info['con_recargo']);
        $this->assertSame(['1 pago', '6 cuotas'], array_column($info['planes'], 'name'));
        $this->assertSame(10000.0, $info['planes'][0]['total_with_surcharge']);
        $this->assertSame(3000.0, $info['planes'][1]['surcharge_amount']);
        $this->assertSame(13000.0, $info['planes'][1]['total_with_surcharge']);
        $this->assertSame(2166.67, $info['planes'][1]['installment_amount']);
    }

    public function test_info_cuotas_es_null_sin_planes_activos_o_sin_total(): void
    {
        $calculadora = app(CalculadoraPedido::class);

        $this->assertNull($calculadora->infoCuotas(5000.0));

        CardPaymentPlan::factory()->inactive()->create();
        $this->assertNull($calculadora->infoCuotas(5000.0));

        CardPaymentPlan::factory()->create();
        $this->assertNull($calculadora->infoCuotas(0.0));
        $this->assertNotNull($calculadora->infoCuotas(5000.0));
    }

    public function test_info_cuotas_sin_recargo_en_ningun_plan(): void
    {
        CardPaymentPlan::factory()->create(['installments' => 3, 'surcharge_percentage' => 0]);

        $this->assertFalse(app(CalculadoraPedido::class)->infoCuotas(900.0)['con_recargo']);
    }
}
