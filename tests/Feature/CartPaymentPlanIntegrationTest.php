<?php

namespace Tests\Feature;

use App\Models\CardPaymentPlan;
use App\Models\Category;
use App\Models\DiscountCode;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * "Forma de pago sugerida" (plan de cuotas con tarjeta de crédito) del carrito.
 * Es un estado de sesión aparte (`cart_payment_plan`), análogo a
 * `cart_discount_code`: se elige en la ficha de producto, NO agrega nada al
 * carrito de productos y el recargo lo recalcula siempre CardSurchargeService
 * sobre el total ya resuelto (subtotal − descuento por código). 100% informativo.
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

    // --- setPaymentPlan --------------------------------------------------------------

    public function test_elegir_un_plan_activo_lo_guarda_en_sesion_como_snapshot(): void
    {
        $plan = $this->plan(['name' => '6 cuotas', 'installments' => 6, 'surcharge_percentage' => 35]);

        $response = $this->postJson(route('cart.payment-plan.set'), ['plan_id' => $plan->id]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertSame([
            'id' => $plan->id,
            'name' => '6 cuotas',
            'installments' => 6,
            'surcharge_percentage' => 35.0,
        ], session('cart_payment_plan'));
    }

    public function test_elegir_un_plan_no_agrega_nada_al_carrito_de_productos(): void
    {
        [$product, $cantidad] = $this->productoEnCarrito();
        $plan = $this->plan();

        $this->withSession(['cart' => [$product->id => $cantidad]])
            ->postJson(route('cart.payment-plan.set'), ['plan_id' => $plan->id])
            ->assertOk();

        $this->assertSame([$product->id => $cantidad], session('cart'));
    }

    public function test_elegir_un_plan_inexistente_es_rechazado(): void
    {
        $response = $this->postJson(route('cart.payment-plan.set'), ['plan_id' => 9999]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
        $this->assertNull(session('cart_payment_plan'));
    }

    public function test_elegir_un_plan_inactivo_es_rechazado(): void
    {
        $plan = $this->plan(['is_active' => false]);

        $response = $this->postJson(route('cart.payment-plan.set'), ['plan_id' => $plan->id]);

        $response->assertStatus(422);
        $this->assertNull(session('cart_payment_plan'));
    }

    public function test_elegir_un_plan_sin_plan_id_falla_la_validacion(): void
    {
        $this->postJson(route('cart.payment-plan.set'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('plan_id');
    }

    // --- removePaymentPlan ---------------------------------------------------------

    public function test_quitar_el_plan_lo_saca_de_la_sesion(): void
    {
        $plan = $this->plan();

        $this->postJson(route('cart.payment-plan.set'), ['plan_id' => $plan->id])->assertOk();
        $this->assertNotNull(session('cart_payment_plan'));

        $response = $this->deleteJson(route('cart.payment-plan.remove'));

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertNull(session('cart_payment_plan'));
    }

    // --- vaciar el carrito limpia todo el estado de sesión del carrito --------------

    public function test_vaciar_el_carrito_limpia_la_forma_de_pago_y_el_codigo_de_descuento(): void
    {
        [$product, $cantidad] = $this->productoEnCarrito(5, 100);
        $this->crearCodigo(['code' => 'OFF10', 'percentage' => 10]);
        $plan = $this->plan();

        $this->withSession(['cart' => [$product->id => $cantidad]])
            ->postJson(route('cart.discount.apply'), ['code' => 'OFF10'])
            ->assertOk();

        $this->postJson(route('cart.payment-plan.set'), ['plan_id' => $plan->id])->assertOk();

        $this->assertSame('OFF10', session('cart_discount_code'));
        $this->assertNotNull(session('cart_payment_plan'));

        $this->deleteJson(route('cart.clear'))->assertOk();

        $this->assertNull(session('cart'));
        $this->assertNull(session('cart_discount_code'));
        $this->assertNull(session('cart_payment_plan'));
    }

    // --- reflejo en props de GET /carrito y /carrito/checkout -----------------------

    public function test_el_plan_elegido_se_refleja_en_carrito_index_con_recargo_sobre_el_total(): void
    {
        [$product, $cantidad] = $this->productoEnCarrito(5, 100); // subtotal 500
        $plan = $this->plan(['installments' => 3, 'surcharge_percentage' => 20]);

        $this->withSession(['cart' => [$product->id => $cantidad]])
            ->postJson(route('cart.payment-plan.set'), ['plan_id' => $plan->id])
            ->assertOk();

        $response = $this->get(route('cart.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Cart/Index')
            ->where('total', 500)
            ->where('paymentPlan.id', $plan->id)
            ->where('paymentPlan.name', '3 cuotas')
            ->where('paymentPlan.installments', 3)
            ->where('paymentPlan.surcharge_percentage', 20)
            ->where('paymentPlan.surcharge_amount', 100)       // 500 * 20%
            ->where('paymentPlan.total_with_surcharge', 600)
            ->where('paymentPlan.installment_amount', 200)     // 600 / 3
            ->where('paymentPlanRemovedReason', null)
        );
    }

    public function test_el_recargo_se_calcula_sobre_el_total_ya_con_descuento_por_codigo(): void
    {
        [$product, $cantidad] = $this->productoEnCarrito(5, 100); // subtotal 500
        $this->crearCodigo(['code' => 'OFF10', 'percentage' => 10]);
        $plan = $this->plan(['installments' => 3, 'surcharge_percentage' => 20]);

        $this->withSession(['cart' => [$product->id => $cantidad]])
            ->postJson(route('cart.discount.apply'), ['code' => 'OFF10'])
            ->assertOk();

        $this->postJson(route('cart.payment-plan.set'), ['plan_id' => $plan->id])->assertOk();

        $response = $this->get(route('cart.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('total', 450) // 500 − 10%
            ->where('paymentPlan.surcharge_amount', 90)        // 450 * 20%
            ->where('paymentPlan.total_with_surcharge', 540)
            ->where('paymentPlan.installment_amount', 180)     // 540 / 3
        );
    }

    public function test_el_plan_elegido_se_refleja_en_checkout(): void
    {
        [$product, $cantidad] = $this->productoEnCarrito(5, 100);
        $plan = $this->plan(['installments' => 1, 'surcharge_percentage' => 10]);

        $this->withSession(['cart' => [$product->id => $cantidad]])
            ->postJson(route('cart.payment-plan.set'), ['plan_id' => $plan->id])
            ->assertOk();

        $response = $this->get(route('cart.checkout'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Cart/Checkout')
            ->where('paymentPlan.installments', 1)
            ->where('paymentPlan.surcharge_amount', 50)        // 500 * 10%
            ->where('paymentPlan.total_with_surcharge', 550)
            ->where('paymentPlan.installment_amount', 550)     // 1 pago, no divide
        );
    }

    public function test_carrito_sin_plan_manda_paymentPlan_null(): void
    {
        [$product, $cantidad] = $this->productoEnCarrito();

        $this->withSession(['cart' => [$product->id => $cantidad]])
            ->get(route('cart.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('paymentPlan', null)
                ->where('paymentPlanRemovedReason', null)
            );
    }

    // --- catálogo de planes para el selector "Forma de pago" ------------------------

    public function test_carrito_index_expone_los_planes_activos_ordenados_para_el_selector(): void
    {
        [$product, $cantidad] = $this->productoEnCarrito();
        $this->plan(['name' => '6 cuotas', 'installments' => 6, 'surcharge_percentage' => 35, 'sort_order' => 2]);
        $this->plan(['name' => '3 cuotas', 'installments' => 3, 'surcharge_percentage' => 20, 'sort_order' => 1]);
        $this->plan(['name' => 'Descontinuado', 'is_active' => false, 'sort_order' => 0]);

        $this->withSession(['cart' => [$product->id => $cantidad]])
            ->get(route('cart.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('cardPaymentPlans', 2)
                ->where('cardPaymentPlans.0.name', '3 cuotas')
                ->where('cardPaymentPlans.0.installments', 3)
                ->where('cardPaymentPlans.0.surcharge_percentage', 20)
                ->where('cardPaymentPlans.1.name', '6 cuotas')
                ->has('cardPaymentPlans.0', fn (Assert $plan) => $plan
                    ->has('id')->has('name')->has('installments')->has('surcharge_percentage')
                )
            );
    }

    public function test_checkout_expone_los_planes_activos_para_el_selector(): void
    {
        [$product, $cantidad] = $this->productoEnCarrito();
        $this->plan(['name' => '3 cuotas', 'installments' => 3, 'surcharge_percentage' => 20]);

        $this->withSession(['cart' => [$product->id => $cantidad]])
            ->get(route('cart.checkout'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cart/Checkout')
                ->has('cardPaymentPlans', 1)
                ->where('cardPaymentPlans.0.name', '3 cuotas')
            );
    }

    public function test_carrito_sin_planes_configurados_manda_un_array_vacio(): void
    {
        [$product, $cantidad] = $this->productoEnCarrito();

        $this->withSession(['cart' => [$product->id => $cantidad]])
            ->get(route('cart.index'))
            ->assertInertia(fn (Assert $page) => $page->has('cardPaymentPlans', 0));
    }

    // --- remoción automática cuando el plan deja de estar disponible ----------------

    public function test_un_plan_desactivado_despues_de_elegirlo_se_remueve_solo_al_recargar(): void
    {
        [$product, $cantidad] = $this->productoEnCarrito(5, 100);
        $plan = $this->plan();

        $this->withSession(['cart' => [$product->id => $cantidad]])
            ->postJson(route('cart.payment-plan.set'), ['plan_id' => $plan->id])
            ->assertOk();

        // Un admin lo desactiva desde el panel mientras sigue en la sesión del carrito.
        $plan->update(['is_active' => false]);

        $response = $this->get(route('cart.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('paymentPlan', null)
            ->where('paymentPlanRemovedReason', fn ($reason) => str_contains((string) $reason, 'ya no está disponible'))
        );

        $this->assertNull(session('cart_payment_plan'));

        // Segunda carga: ya no queda nada que remover, no se reporta motivo.
        $this->get(route('cart.index'))->assertInertia(fn (Assert $page) => $page
            ->where('paymentPlan', null)
            ->where('paymentPlanRemovedReason', null)
        );
    }
}
