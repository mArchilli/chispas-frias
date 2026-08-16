<?php

namespace Tests\Feature;

use App\Enums\EstadoOrden;
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
