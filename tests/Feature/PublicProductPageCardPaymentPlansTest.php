<?php

namespace Tests\Feature;

use App\Models\CardPaymentPlan;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPriceTier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * ProductController@show expone el catálogo de planes de cuotas ACTIVOS para el
 * simulador de recargo por pago con tarjeta de la ficha
 * (resources/js/Components/CardPaymentPlanSimulator.jsx + utils/cardSurcharge.js).
 *
 * Es 100% informativo y por producto: el mirror JS calcula el recargo en el
 * cliente sobre el precio ya mostrado del producto, sin tocar el carrito.
 */
class PublicProductPageCardPaymentPlansTest extends TestCase
{
    use RefreshDatabase;

    private function producto(array $attrs = []): Product
    {
        return Product::factory()
            ->for(Category::factory())
            ->create(array_merge(['is_active' => true, 'stock' => 20], $attrs));
    }

    public function test_show_expone_solo_los_planes_activos_ordenados_por_sort_order(): void
    {
        $product = $this->producto();

        CardPaymentPlan::factory()->create([
            'name' => '6 cuotas', 'installments' => 6, 'surcharge_percentage' => 35, 'sort_order' => 3,
        ]);
        CardPaymentPlan::factory()->create([
            'name' => '1 cuota', 'installments' => 1, 'surcharge_percentage' => 10, 'sort_order' => 1,
        ]);
        CardPaymentPlan::factory()->create([
            'name' => '3 cuotas', 'installments' => 3, 'surcharge_percentage' => 20, 'sort_order' => 2,
        ]);
        CardPaymentPlan::factory()->inactive()->create([
            'name' => 'Descontinuado', 'installments' => 12, 'surcharge_percentage' => 50, 'sort_order' => 0,
        ]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Products/Show')
                ->has('cardPaymentPlans', 3)
                ->where('cardPaymentPlans.0.name', '1 cuota')
                ->where('cardPaymentPlans.0.installments', 1)
                ->where('cardPaymentPlans.0.surcharge_percentage', 10)
                ->where('cardPaymentPlans.1.name', '3 cuotas')
                ->where('cardPaymentPlans.1.surcharge_percentage', 20)
                ->where('cardPaymentPlans.2.name', '6 cuotas')
                ->where('cardPaymentPlans.2.installments', 6)
                ->has('cardPaymentPlans.0', fn (Assert $plan) => $plan
                    ->has('id')
                    ->has('name')
                    ->has('installments')
                    ->has('surcharge_percentage')
                )
            );
    }

    public function test_show_sin_planes_manda_un_array_vacio(): void
    {
        $product = $this->producto();

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Products/Show')
                ->has('cardPaymentPlans', 0)
            );
    }

    /**
     * El simulador (CardPaymentPlanSimulator.jsx) calcula el recargo sobre
     * `precioFinalConOpciones * quantity`, con la cantidad resuelta en el cliente.
     * Para que esa multiplicación sea correcta, la data que la alimenta tiene que
     * ser POR UNIDAD: el precio de entrada y cada tier son unitarios, el backend
     * nunca los pre-multiplica.
     */
    public function test_show_expone_precios_por_unidad_para_el_simulador(): void
    {
        $product = $this->producto(['price' => 1000]);
        ProductPriceTier::factory()->create([
            'product_id' => $product->id,
            'cantidad_minima' => 10,
            'precio_unitario' => 800,
        ]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Products/Show')
                ->where('product.pricing.final_price', 1000)
                ->where('product.price_tiers.0.precio_unitario', 800)
            );
    }

    public function test_show_hidrata_el_plan_ya_elegido_en_la_sesion_del_carrito(): void
    {
        $product = $this->producto();
        $plan = CardPaymentPlan::factory()->create([
            'name' => '3 cuotas', 'installments' => 3, 'surcharge_percentage' => 20,
        ]);

        $this->withSession(['cart_payment_plan' => [
            'id' => $plan->id,
            'name' => $plan->name,
            'installments' => 3,
            'surcharge_percentage' => 20.0,
        ]])->get(route('products.show', $product))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Products/Show')
                ->where('selectedCardPaymentPlanId', $plan->id)
            );
    }

    public function test_show_sin_plan_en_sesion_manda_selected_card_payment_plan_id_null(): void
    {
        $product = $this->producto();
        CardPaymentPlan::factory()->create();

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('selectedCardPaymentPlanId', null)
            );
    }

    public function test_show_no_hidrata_un_plan_desactivado_despues_de_elegirlo(): void
    {
        $product = $this->producto();
        $plan = CardPaymentPlan::factory()->inactive()->create();

        $this->withSession(['cart_payment_plan' => ['id' => $plan->id]])
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('selectedCardPaymentPlanId', null)
            );
    }
}
