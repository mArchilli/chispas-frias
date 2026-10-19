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
 * CalculadoraPedido es pura: calcula el pedido (ítems, cupón validado sin consumir, plan de
 * tarjeta y totales) sin escribir en la base ni en la sesión. El comportamiento del checkout
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
        $plan = CardPaymentPlan::factory()->create(['installments' => 3, 'surcharge_percentage' => 20]);
        $calculadora = app(CalculadoraPedido::class);
        $lineas = [['line_key' => 'k', 'product_id' => $p->id, 'quantity' => 4, 'variant_id' => null, 'addon_selections' => [], 'custom_color_text' => null]];

        $resultado = null;
        $escrituras = $this->escrituras(function () use ($calculadora, $lineas, $plan, &$resultado) {
            $calculo = $calculadora->calcularItems($lineas);
            $resultado = $calculadora->aplicarDescuentoYPlan($calculo, 'verano10', ['id' => $plan->id]);
        });

        $this->assertSame([], $escrituras, 'La calculadora no puede escribir en la base.');

        $this->assertEquals(4000.0, $resultado->subtotal);
        $this->assertSame($codigo->id, $resultado->codigoDescuento->id);
        $this->assertSame(400.0, $resultado->descuento);
        $this->assertSame(3600.0, $resultado->total);
        $this->assertSame(720.0, $resultado->planPago['surcharge_amount']);
        $this->assertSame(4320.0, $resultado->planPago['total_with_surcharge']);
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
        $final = $calculadora->aplicarDescuentoYPlan($calculo, null, ['id' => 999]);

        // El plan inexistente se informa, no se borra de la sesión: eso lo decide el controller.
        $this->assertNull($final->planPago);
        $this->assertSame('La forma de pago que habías elegido ya no está disponible.', $final->motivoPlanDescartado);
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

        $escrituras = $this->escrituras(fn () => $calculadora->aplicarDescuentoYPlan($calculo, 'VIEJO', null));
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
}
