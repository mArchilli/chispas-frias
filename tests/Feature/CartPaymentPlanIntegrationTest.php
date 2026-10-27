<?php

namespace Tests\Feature;

use App\Models\CardPaymentPlan;
use App\Models\Category;
use App\Models\DiscountCode;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Cuotas con tarjeta de crédito en el carrito, el checkout y el drawer: SOLO información. Ya no hay forma de pago "elegida"
 * (`cart_payment_plan`): el servidor calcula, sobre el total del carrito (subtotal − descuento por código), lo que costaría pagar
 * con cada plan ACTIVO ("$X en efectivo o transferencia · o hasta N cuotas con tarjeta") y el medio se coordina por WhatsApp.
 */
class CartPaymentPlanIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function plan(array $overrides = []): CardPaymentPlan
    {
        return CardPaymentPlan::factory()->create(array_merge([
            'name' => '3 cuotas',
            'installments' => 3,
            'surcharge_percentage' => 20,
            'sort_order' => 0,
            'is_active' => true,
        ], $overrides));
    }

    private function crearCodigo(array $overrides = []): DiscountCode
    {
        return DiscountCode::create(array_merge([
            'code' => 'CODE'.random_int(100000, 999999),
            'description' => null,
            'percentage' => 10,
            'min_purchase_amount' => null,
            'usage_limit' => null,
            'usage_count' => 0,
            'start_date' => null,
            'end_date' => null,
            'is_active' => true,
        ], $overrides));
    }

    private function productoEnCarrito(int $cantidad = 5, float $precio = 100): array
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create(['price' => $precio, 'stock' => 50]);

        return [$product, $cantidad];
    }

    // --- ya no se elige una forma de pago --------------------------------------------

    public function test_ya_no_hay_rutas_para_elegir_la_forma_de_pago(): void
    {
        $this->assertFalse(Route::has('cart.payment-plan.set'));
        $this->assertFalse(Route::has('cart.payment-plan.remove'));

        $this->postJson('/carrito/forma-pago', ['plan_id' => $this->plan()->id])->assertStatus(405)->assertHeader('Allow');
    }

    public function test_un_plan_viejo_en_la_sesion_se_ignora(): void
    {
        [$product, $cantidad] = $this->productoEnCarrito(5, 100);
        $plan = $this->plan(['installments' => 6, 'surcharge_percentage' => 35]);

        $this->withSession([
            'cart' => [$product->id => $cantidad],
            'cart_payment_plan' => ['id' => $plan->id, 'name' => 'x', 'installments' => 6, 'surcharge_percentage' => 35],
        ])->get(route('cart.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->missing('paymentPlan')
                ->missing('paymentPlanRemovedReason')
                ->missing('cardPaymentPlans')
                ->where('cuotas.max_cuotas', 6)
            );
    }

    public function test_vaciar_el_carrito_limpia_el_codigo_de_descuento_y_un_plan_viejo(): void
    {
        [$product, $cantidad] = $this->productoEnCarrito();

        $this->withSession([
            'cart' => [$product->id => $cantidad],
            'cart_discount_code' => 'ALGO',
            'cart_payment_plan' => ['id' => 1],
        ])->deleteJson(route('cart.clear'))->assertOk();

        $this->assertNull(session('cart'));
        $this->assertNull(session('cart_discount_code'));
        $this->assertNull(session('cart_payment_plan'));
    }

    // --- información de cuotas calculada en el servidor ------------------------------

    public function test_el_carrito_expone_las_cuotas_calculadas_sobre_el_total(): void
    {
        [$product, $cantidad] = $this->productoEnCarrito(5, 100);
        $this->plan(['name' => '6 cuotas', 'installments' => 6, 'surcharge_percentage' => 30, 'sort_order' => 1]);
        $this->plan(['name' => '1 pago', 'installments' => 1, 'surcharge_percentage' => 0, 'sort_order' => 0]);

        $this->withSession(['cart' => [$product->id => $cantidad]])
            ->get(route('cart.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cart/Index')
                ->where('cuotas.efectivo', 500)
                ->where('cuotas.max_cuotas', 6)
                ->where('cuotas.con_recargo', true)
                ->has('cuotas.planes', 2)
                ->where('cuotas.planes.0.name', '1 pago')
                ->where('cuotas.planes.1.name', '6 cuotas')
                ->where('cuotas.planes.1.surcharge_amount', 150)
                ->where('cuotas.planes.1.total_with_surcharge', 650)
                ->where('cuotas.planes.1.installment_amount', 108.33)
            );
    }

    public function test_las_cuotas_se_calculan_sobre_el_total_ya_con_descuento_por_codigo(): void
    {
        [$product, $cantidad] = $this->productoEnCarrito(5, 100);
        $codigo = $this->crearCodigo(['percentage' => 10]);
        $this->plan(['installments' => 3, 'surcharge_percentage' => 20]);

        $this->withSession(['cart' => [$product->id => $cantidad], 'cart_discount_code' => $codigo->code])
            ->get(route('cart.index'))
            ->assertInertia(fn (Assert $page) => $page
                // subtotal 500 − 10% = 450; recargo 20% = 90; total con recargo 540; 3 cuotas de 180.
                ->where('total', 450)
                ->where('cuotas.efectivo', 450)
                ->where('cuotas.planes.0.surcharge_amount', 90)
                ->where('cuotas.planes.0.total_with_surcharge', 540)
                ->where('cuotas.planes.0.installment_amount', 180)
            );
    }

    public function test_el_checkout_expone_las_cuotas(): void
    {
        [$product, $cantidad] = $this->productoEnCarrito(5, 100);
        $this->plan(['name' => '3 cuotas', 'installments' => 3, 'surcharge_percentage' => 20]);

        $this->withSession(['cart' => [$product->id => $cantidad]])
            ->get(route('cart.checkout'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cart/Checkout')
                ->where('cuotas.efectivo', 500)
                ->where('cuotas.planes.0.name', '3 cuotas')
                ->missing('cardPaymentPlans')
            );
    }

    public function test_el_drawer_expone_las_cuotas_en_el_resumen_del_carrito(): void
    {
        [$product, $cantidad] = $this->productoEnCarrito(5, 100);
        $this->plan(['installments' => 12, 'surcharge_percentage' => 50]);

        $this->withSession(['cart' => [$product->id => $cantidad]])
            ->getJson(route('cart.preview'))
            ->assertOk()
            ->assertJsonPath('cuotas.efectivo', 500)
            ->assertJsonPath('cuotas.max_cuotas', 12)
            ->assertJsonPath('cuotas.planes.0.total_with_surcharge', 750);
    }

    public function test_solo_cuentan_los_planes_activos(): void
    {
        [$product, $cantidad] = $this->productoEnCarrito();
        $this->plan(['name' => 'activo', 'installments' => 3]);
        $this->plan(['name' => 'apagado', 'installments' => 12, 'is_active' => false]);

        $this->withSession(['cart' => [$product->id => $cantidad]])
            ->get(route('cart.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('cuotas.max_cuotas', 3)
                ->has('cuotas.planes', 1)
                ->where('cuotas.planes.0.name', 'activo')
            );
    }

    public function test_sin_planes_activos_las_cuotas_son_null(): void
    {
        [$product, $cantidad] = $this->productoEnCarrito();

        $this->withSession(['cart' => [$product->id => $cantidad]])
            ->get(route('cart.index'))
            ->assertInertia(fn (Assert $page) => $page->where('cuotas', null));

        $this->withSession(['cart' => [$product->id => $cantidad]])
            ->getJson(route('cart.preview'))
            ->assertJsonPath('cuotas', null);
    }
}
