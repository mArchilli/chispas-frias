<?php

namespace Tests\Feature;

use App\Models\CardPaymentPlan;
use App\Models\Category;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Flujo completo de checkout (POST /carrito/whatsapp) y la forma de pago.
 *
 * La forma de pago ya no se elige en la web: el medio se coordina por WhatsApp. El mensaje dice "a coordinar por WhatsApp", la orden
 * no guarda ningún plan de tarjeta (las columnas `card_payment_plan_id` / `surcharge_*` quedan solo para los pedidos históricos) y un
 * `cart_payment_plan` que haya quedado en una sesión vieja se ignora y se borra. Nada de esto llama a la API de Mercado Pago.
 */
class CartWhatsAppPaymentPlanTest extends TestCase
{
    use RefreshDatabase;

    private function validCustomerData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Juana Pérez',
            'province' => 'buenos-aires',
            'phone' => '11 2233-4455',
            'observations' => 'Tocar timbre.',
        ], $overrides);
    }

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

    private function producto(float $precio = 1000, int $stock = 50): Product
    {
        $category = Category::factory()->create();

        return Product::factory()->for($category)->create(['price' => $precio, 'stock' => $stock]);
    }

    public function test_the_order_and_the_message_leave_the_payment_to_be_coordinated_by_whatsapp(): void
    {
        $product = $this->producto(1000);
        $this->plan(); // hay planes activos: igual no se elige ninguno

        $response = $this->withSession(['cart' => [$product->id => 1]])
            ->postJson(route('cart.whatsapp'), ['customer_data' => $this->validCustomerData()]);

        $response->assertOk();
        $this->assertNull($response->json('payment_plan'));

        $order = Order::first();

        $this->assertNull($order->card_payment_plan_id);
        $this->assertNull($order->payment_plan_name);
        $this->assertNull($order->payment_plan_installments);
        $this->assertNull($order->surcharge_percentage);
        $this->assertNull($order->surcharge_amount);
        $this->assertNull($order->total_with_surcharge);
        $this->assertEquals(1000.0, (float) $order->total);

        $this->assertStringContainsString('Forma de pago:* a coordinar por WhatsApp', $order->mensaje_whatsapp);
        $this->assertStringContainsString('*TOTAL: $1.000*', $order->mensaje_whatsapp);
        $this->assertStringEndsWith('a coordinar por WhatsApp', $order->mensaje_whatsapp);
        $this->assertStringNotContainsString('Tarjeta de crédito', $order->mensaje_whatsapp);
        $this->assertStringNotContainsString('Mercado Pago', $order->mensaje_whatsapp);
        $this->assertSame($order->mensaje_whatsapp, $response->json('message'));
    }

    public function test_a_payment_plan_left_in_an_old_session_is_ignored_and_cleared(): void
    {
        $product = $this->producto(1000); // 2 x 1000 = 2000
        $plan = $this->plan(['name' => '3 cuotas', 'installments' => 3, 'surcharge_percentage' => 20]);

        $response = $this->withSession([
            'cart' => [$product->id => 2],
            // Una sesión de antes de este cambio: el plan elegido ya no significa nada.
            'cart_payment_plan' => ['id' => $plan->id, 'name' => '3 cuotas', 'installments' => 3, 'surcharge_percentage' => 20],
        ])->postJson(route('cart.whatsapp'), ['customer_data' => $this->validCustomerData()]);

        $response->assertOk();
        $this->assertNull($response->json('payment_plan'));

        $order = Order::first();

        $this->assertNull($order->card_payment_plan_id);
        $this->assertNull($order->surcharge_amount);
        $this->assertNull($order->total_with_surcharge);
        $this->assertEquals(2000.0, (float) $order->total);
        $this->assertStringNotContainsString('Tarjeta de crédito', $order->mensaje_whatsapp);
        $this->assertStringNotContainsString('Total a cobrar', $order->mensaje_whatsapp);
        $this->assertStringNotContainsString('Generar link de pago', $order->mensaje_whatsapp);
        $this->assertStringContainsString('a coordinar por WhatsApp', $order->mensaje_whatsapp);

        $this->assertNull(session('cart_payment_plan'));
    }

    public function test_the_discount_code_still_applies_and_a_plan_id_sent_by_the_client_is_ignored(): void
    {
        $product = $this->producto(1000); // 2 x 1000 = 2000
        $this->crearCodigo(['code' => 'OFF10', 'percentage' => 10]);
        $plan = $this->plan(['installments' => 1, 'surcharge_percentage' => 10]);

        $response = $this->withSession([
            'cart' => [$product->id => 2],
            'cart_discount_code' => 'OFF10',
        ])->postJson(route('cart.whatsapp'), [
            'customer_data' => $this->validCustomerData(),
            'plan_pago_tarjeta_id' => $plan->id,
            'payment_plan_id' => $plan->id,
        ]);

        $response->assertOk();

        $order = Order::first();

        // total = 2000 − 10% = 1800, sin recargo en la orden.
        $this->assertEquals(1800.0, (float) $order->total);
        $this->assertNull($order->card_payment_plan_id);
        $this->assertNull($order->total_with_surcharge);
        $this->assertStringContainsString('Descuento (10%): -$200', $order->mensaje_whatsapp);
        $this->assertStringContainsString('*TOTAL: $1.800*', $order->mensaje_whatsapp);
    }
}
