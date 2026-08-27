<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Models\CardPaymentPlan;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminCardPaymentPlanTest extends TestCase
{
    use RefreshDatabase;

    private function crearPlan(array $overrides = []): CardPaymentPlan
    {
        return CardPaymentPlan::create(array_merge([
            'name' => 'Plan '.random_int(100000, 999999),
            'installments' => 3,
            'surcharge_percentage' => 20,
            'sort_order' => 0,
            'is_active' => true,
        ], $overrides));
    }

    private function marcarComoUsado(CardPaymentPlan $plan): void
    {
        Order::factory()->create(['card_payment_plan_id' => $plan->id]);
    }

    // --- acceso / permisos -------------------------------------------------------

    public function test_index_requires_authentication(): void
    {
        $this->get(route('admin.card-payment-plans.index'))->assertRedirect(route('login'));
    }

    public function test_vendedor_no_puede_acceder(): void
    {
        $vendedor = User::factory()->create(['role' => RolUsuario::Vendedor]);

        $this->actingAs($vendedor)->get(route('admin.card-payment-plans.index'))->assertForbidden();
        $this->actingAs($vendedor)->get(route('admin.card-payment-plans.create'))->assertForbidden();
    }

    public function test_cliente_no_puede_acceder(): void
    {
        $cliente = User::factory()->create(['role' => RolUsuario::Cliente]);

        $this->actingAs($cliente)->get(route('admin.card-payment-plans.index'))->assertForbidden();
    }

    // --- index -----------------------------------------------------------------

    public function test_index_lista_los_planes_ordenados_por_sort_order(): void
    {
        $admin = User::factory()->create();
        $this->crearPlan(['name' => 'Seis', 'sort_order' => 3]);
        $this->crearPlan(['name' => 'Una', 'sort_order' => 1]);

        $this->actingAs($admin)->get(route('admin.card-payment-plans.index'))->assertInertia(
            fn (Assert $page) => $page
                ->component('Admin/CardPaymentPlans/Index')
                ->where('plans.0.name', 'Una')
                ->where('plans.1.name', 'Seis')
        );
    }

    public function test_index_marca_los_planes_usados_en_ordenes(): void
    {
        $admin = User::factory()->create();
        $libre = $this->crearPlan(['name' => 'Libre', 'sort_order' => 1]);
        $usado = $this->crearPlan(['name' => 'Usado', 'sort_order' => 2]);
        $this->marcarComoUsado($usado);

        $this->actingAs($admin)->get(route('admin.card-payment-plans.index'))->assertInertia(
            fn (Assert $page) => $page
                ->where('plans.0.id', $libre->id)->where('plans.0.en_uso', false)
                ->where('plans.1.id', $usado->id)->where('plans.1.en_uso', true)
        );
    }

    // --- store -----------------------------------------------------------------

    public function test_store_crea_un_plan(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.card-payment-plans.store'), [
            'name' => '3 cuotas',
            'installments' => 3,
            'surcharge_percentage' => 20,
            'sort_order' => 2,
            'is_active' => true,
        ])->assertRedirect(route('admin.card-payment-plans.index'));

        $plan = CardPaymentPlan::firstOrFail();
        $this->assertSame('3 cuotas', $plan->name);
        $this->assertSame(3, $plan->installments);
        $this->assertEquals(20.0, (float) $plan->surcharge_percentage);
        $this->assertSame(2, $plan->sort_order);
        $this->assertTrue($plan->is_active);
    }

    public function test_store_usa_defaults_para_sort_order_e_is_active(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.card-payment-plans.store'), [
            'name' => 'Simple',
            'installments' => 1,
            'surcharge_percentage' => 10,
        ])->assertRedirect(route('admin.card-payment-plans.index'));

        $plan = CardPaymentPlan::firstOrFail();
        $this->assertSame(0, $plan->sort_order);
        $this->assertTrue($plan->is_active);
    }

    public function test_store_valida_los_campos_obligatorios(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.card-payment-plans.store'), [
            'name' => '',
            'installments' => 0,
            'surcharge_percentage' => -5,
        ])->assertSessionHasErrors(['name', 'installments', 'surcharge_percentage']);

        $this->assertSame(0, CardPaymentPlan::count());
    }

    // --- update ----------------------------------------------------------------

    public function test_update_edita_un_plan(): void
    {
        $admin = User::factory()->create();
        $plan = $this->crearPlan(['name' => 'Viejo', 'surcharge_percentage' => 10]);

        $this->actingAs($admin)->put(route('admin.card-payment-plans.update', $plan), [
            'name' => 'Nuevo',
            'installments' => 6,
            'surcharge_percentage' => 35,
            'sort_order' => 5,
            'is_active' => false,
        ])->assertRedirect(route('admin.card-payment-plans.index'));

        $plan->refresh();
        $this->assertSame('Nuevo', $plan->name);
        $this->assertSame(6, $plan->installments);
        $this->assertEquals(35.0, (float) $plan->surcharge_percentage);
        $this->assertFalse($plan->is_active);
    }

    // --- toggle-status -------------------------------------------------------------

    public function test_toggle_status_invierte_is_active(): void
    {
        $admin = User::factory()->create();
        $plan = $this->crearPlan(['is_active' => true]);

        $this->actingAs($admin)->patch(route('admin.card-payment-plans.toggle-status', $plan))->assertRedirect();
        $this->assertFalse($plan->fresh()->is_active);

        $this->actingAs($admin)->patch(route('admin.card-payment-plans.toggle-status', $plan))->assertRedirect();
        $this->assertTrue($plan->fresh()->is_active);
    }

    // --- destroy -----------------------------------------------------------------

    public function test_destroy_elimina_un_plan_sin_usar(): void
    {
        $admin = User::factory()->create();
        $plan = $this->crearPlan();

        $this->actingAs($admin)->delete(route('admin.card-payment-plans.destroy', $plan))
            ->assertRedirect(route('admin.card-payment-plans.index'));

        $this->assertSame(0, CardPaymentPlan::count());
    }

    public function test_destroy_bloquea_un_plan_usado_en_una_orden(): void
    {
        $admin = User::factory()->create();
        $plan = $this->crearPlan();
        $this->marcarComoUsado($plan);

        $this->actingAs($admin)->delete(route('admin.card-payment-plans.destroy', $plan))
            ->assertSessionHasErrors('error');

        $this->assertNotNull($plan->fresh());
    }
}
