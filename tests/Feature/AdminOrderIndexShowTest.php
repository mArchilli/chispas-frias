<?php

namespace Tests\Feature;

use App\Enums\EstadoOrden;
use App\Models\CardPaymentPlan;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminOrderIndexShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_orders_most_recent_first(): void
    {
        $admin = User::factory()->create();

        $older = Order::factory()->create(['name' => 'Vieja', 'created_at' => now()->subDays(2)]);
        $newer = Order::factory()->create(['name' => 'Nueva', 'created_at' => now()]);

        $response = $this->actingAs($admin)->get(route('admin.orders.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Index')
            ->where('orders.data.0.id', $newer->id)
            ->where('orders.data.1.id', $older->id)
        );
    }

    public function test_index_filters_by_estado(): void
    {
        $admin = User::factory()->create();

        $pendiente = Order::factory()->create(['estado' => EstadoOrden::Pendiente]);
        Order::factory()->despachado()->create();
        Order::factory()->cancelado()->create();

        $response = $this->actingAs($admin)->get(route('admin.orders.index', ['estado' => 'pendiente']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Index')
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $pendiente->id)
        );
    }

    public function test_index_filters_by_search_term(): void
    {
        $admin = User::factory()->create();

        $match = Order::factory()->create(['name' => 'Juana', 'lastname' => 'Pérez', 'dni' => '30111222']);
        Order::factory()->create(['name' => 'Otro', 'lastname' => 'Distinto', 'dni' => '11223344']);

        $response = $this->actingAs($admin)->get(route('admin.orders.index', ['search' => 'Juana']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Index')
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $match->id)
        );
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->get(route('admin.orders.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_show_returns_full_order_detail_including_items(): void
    {
        $admin = User::factory()->create();
        $product = Product::factory()->create();

        $order = Order::factory()->create([
            'estado' => EstadoOrden::Pendiente,
            'mensaje_whatsapp' => 'Mensaje de prueba',
        ]);
        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_title' => $product->title,
            'cantidad' => 3,
            'precio_unitario' => 150,
            'subtotal' => 450,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.orders.show', $order));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Show')
            ->where('order.id', $order->id)
            ->where('order.name', $order->name)
            ->where('order.dni', $order->dni)
            ->where('order.email', $order->email)
            ->where('order.mensaje_whatsapp', 'Mensaje de prueba')
            ->where('order.estado', 'pendiente')
            ->has('order.items', 1)
            ->where('order.items.0.id', $item->id)
            ->where('order.items.0.product_title', $product->title)
            ->where('order.items.0.cantidad', 3)
            ->where('order.transiciones_disponibles', ['despachado', 'cancelado'])
        );
    }

    public function test_show_exposes_the_variant_and_addons_snapshot_of_each_item(): void
    {
        $admin = User::factory()->create();
        $product = Product::factory()->create();

        $order = Order::factory()->create(['estado' => EstadoOrden::Pendiente]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_title' => $product->title,
            'variant_name' => 'A elección',
            'variant_color_hex' => '#8b5cf6',
            'custom_color_text' => 'Violeta con destellos',
            'addons_selected' => [
                ['addon_id' => 7, 'name' => 'Grabado láser', 'price' => 150.0, 'custom_text' => 'Feliz cumple'],
            ],
            'addons_total' => 150,
            'cantidad' => 1,
            'precio_unitario' => 1150,
            'base_unit_price' => 1000,
            'subtotal' => 1150,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.orders.show', $order));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Show')
            ->where('order.items.0.variant_name', 'A elección')
            ->where('order.items.0.custom_color_text', 'Violeta con destellos')
            ->where('order.items.0.addons_selected.0.name', 'Grabado láser')
            ->where('order.items.0.addons_selected.0.custom_text', 'Feliz cumple')
        );
    }

    public function test_show_returns_empty_options_for_a_plain_item(): void
    {
        $admin = User::factory()->create();
        $product = Product::factory()->create();

        $order = Order::factory()->create();
        $order->items()->create([
            'product_id' => $product->id,
            'product_title' => $product->title,
            'cantidad' => 2,
            'precio_unitario' => 100,
            'subtotal' => 200,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.orders.show', $order));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Show')
            ->where('order.items.0.variant_name', null)
            ->where('order.items.0.custom_color_text', null)
            ->where('order.items.0.addons_selected', [])
        );
    }

    public function test_show_exposes_the_card_payment_plan_snapshot_with_the_amount_to_charge(): void
    {
        $admin = User::factory()->create();
        $product = Product::factory()->create();
        $plan = CardPaymentPlan::factory()->create(['name' => '3 cuotas', 'installments' => 3, 'surcharge_percentage' => 20]);

        $order = Order::factory()->create([
            'total' => 2000,
            'card_payment_plan_id' => $plan->id,
            'payment_plan_name' => '3 cuotas',
            'payment_plan_installments' => 3,
            'surcharge_percentage' => 20,
            'surcharge_amount' => 400,
            'total_with_surcharge' => 2400,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_title' => $product->title,
            'cantidad' => 1,
            'precio_unitario' => 2000,
            'subtotal' => 2000,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.orders.show', $order));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Show')
            ->where('order.payment_plan.name', '3 cuotas')
            ->where('order.payment_plan.installments', 3)
            ->where('order.payment_plan.surcharge_percentage', 20)
            ->where('order.payment_plan.surcharge_amount', 400)
            ->where('order.payment_plan.total_with_surcharge', 2400)
            ->where('order.payment_plan.installment_amount', 800) // 2400 / 3, derivado
            ->where('order.payment_plan.formatted_total_with_surcharge', '$2.400')
            ->where('order.payment_plan.formatted_installment_amount', '$800')
            // El total del pedido no cambia por el recargo.
            ->where('order.total', 2000)
        );
    }

    public function test_show_returns_a_null_payment_plan_for_a_cash_order(): void
    {
        $admin = User::factory()->create();
        $order = Order::factory()->create();

        $response = $this->actingAs($admin)->get(route('admin.orders.show', $order));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Show')
            ->where('order.payment_plan', null)
        );
    }

    public function test_show_exposes_no_transitions_for_a_cancelled_order(): void
    {
        $admin = User::factory()->create();
        $order = Order::factory()->cancelado()->create();

        $response = $this->actingAs($admin)->get(route('admin.orders.show', $order));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Show')
            ->where('order.transiciones_disponibles', [])
        );
    }

    public function test_show_requires_authentication(): void
    {
        $order = Order::factory()->create();

        $response = $this->get(route('admin.orders.show', $order));

        $response->assertRedirect(route('login'));
    }
}
