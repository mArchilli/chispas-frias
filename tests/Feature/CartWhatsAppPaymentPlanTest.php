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
 * Flujo completo de checkout (POST /carrito/whatsapp) con una "forma de pago
 * sugerida" (plan de cuotas con tarjeta de crédito) elegida en el carrito.
 *
 * El recargo se recalcula SIEMPRE server-side con CardSurchargeService sobre el
 * total ya resuelto (subtotal − descuento por código) — nunca se confía en un
 * total del frontend —, se snapshotea en la orden (card_payment_plan_id +
 * payment_plan_name / payment_plan_installments / surcharge_* /
 * total_with_surcharge) y agrega un bloque accionable al mensaje de WhatsApp
 * con el monto exacto para que el vendedor genere el link de pago a mano.
 *
 * Sin plan elegido, la orden y el mensaje quedan exactamente como hoy. Nada de
 * esto llama a la API de Mercado Pago.
 */
class CartWhatsAppPaymentPlanTest extends TestCase
{
    use RefreshDatabase;

    private function validCustomerData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Juana',
            'lastname' => 'Pérez',
            'dni' => '30111222',
            'province' => 'buenos-aires',
            'city' => 'La Plata',
            'postal_code' => '1900',
            'phone' => '1122334455',
            'email' => 'juana@example.com',
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

    public function test_snapshots_the_payment_plan_on_the_order_and_recomputes_the_surcharge_server_side(): void
    {
        $product = $this->producto(1000); // 2 x 1000 = 2000
        $plan = $this->plan(['name' => '3 cuotas', 'installments' => 3, 'surcharge_percentage' => 20]);

        $response = $this->withSession([
            'cart' => [$product->id => 2],
            // El frontend guarda un snapshot pero jamás manda el total ni el
            // recargo: acá van valores basura a propósito, el backend los ignora
            // y recalcula todo contra la DB.
            'cart_payment_plan' => ['id' => $plan->id, 'name' => 'basura', 'installments' => 99, 'surcharge_percentage' => 999],
        ])->postJson(route('cart.whatsapp'), [
            'customer_data' => $this->validCustomerData(),
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'total' => 2000.0,
            'payment_plan' => [
                'id' => $plan->id,
                'name' => '3 cuotas',
                'installments' => 3,
                'surcharge_percentage' => 20,
                'surcharge_amount' => 400,       // 2000 * 20%
                'total_with_surcharge' => 2400,
                'installment_amount' => 800,     // 2400 / 3
            ],
        ]);

        $order = Order::first();

        $this->assertSame($plan->id, $order->card_payment_plan_id);
        $this->assertSame('3 cuotas', $order->payment_plan_name);
        $this->assertSame(3, $order->payment_plan_installments);
        $this->assertEquals(20.0, (float) $order->surcharge_percentage);
        $this->assertEquals(400.0, (float) $order->surcharge_amount);
        $this->assertEquals(2400.0, (float) $order->total_with_surcharge);
        // El total del pedido NO cambia: el recargo es informativo.
        $this->assertEquals(2000.0, (float) $order->total);

        $this->assertStringContainsString('💳 *Forma de pago: Tarjeta de crédito*', $order->mensaje_whatsapp);
        $this->assertStringContainsString('3 cuotas sin interés mensual, recargo 20%', $order->mensaje_whatsapp);
        $this->assertStringContainsString('Total a cobrar: $2.400 ($800 c/u)', $order->mensaje_whatsapp);
        $this->assertStringContainsString('Generar link de pago en Mercado Pago por $2.400', $order->mensaje_whatsapp);

        $this->assertNull(session('cart_payment_plan'));
    }

    public function test_an_order_without_a_payment_plan_keeps_the_message_and_fields_untouched(): void
    {
        $product = $this->producto(1000);

        $response = $this->withSession([
            'cart' => [$product->id => 1],
        ])->postJson(route('cart.whatsapp'), [
            'customer_data' => $this->validCustomerData(),
        ]);

        $response->assertOk();
        $this->assertNull($response->json('payment_plan'));

        $order = Order::first();

        $this->assertNull($order->card_payment_plan_id);
        $this->assertNull($order->payment_plan_name);
        $this->assertNull($order->payment_plan_installments);
        $this->assertNull($order->surcharge_percentage);
        $this->assertNull($order->surcharge_amount);
        $this->assertNull($order->total_with_surcharge);

        $this->assertStringNotContainsString('Tarjeta de crédito', $order->mensaje_whatsapp);
        $this->assertStringNotContainsString('Mercado Pago', $order->mensaje_whatsapp);
        // El mensaje termina en la línea de TOTAL, igual que siempre.
        $this->assertStringEndsWith('*TOTAL: $1.000*', $order->mensaje_whatsapp);
    }

    public function test_the_surcharge_is_computed_on_the_total_already_net_of_the_discount_code(): void
    {
        $product = $this->producto(1000); // 2 x 1000 = 2000
        $this->crearCodigo(['code' => 'OFF10', 'percentage' => 10]);
        $plan = $this->plan(['installments' => 1, 'surcharge_percentage' => 10]);

        $response = $this->withSession([
            'cart' => [$product->id => 2],
            'cart_discount_code' => 'OFF10',
            'cart_payment_plan' => ['id' => $plan->id],
        ])->postJson(route('cart.whatsapp'), [
            'customer_data' => $this->validCustomerData(),
        ]);

        $response->assertOk();

        $order = Order::first();

        // total = 2000 − 10% = 1800; recargo 10% sobre 1800 = 180; total = 1980.
        $this->assertEquals(1800.0, (float) $order->total);
        $this->assertEquals(180.0, (float) $order->surcharge_amount);
        $this->assertEquals(1980.0, (float) $order->total_with_surcharge);
        $this->assertSame(1, $order->payment_plan_installments);

        // Un solo pago: sin "cuotas" ni "c/u" en el mensaje.
        $this->assertStringContainsString('pago único, recargo 10%', $order->mensaje_whatsapp);
        $this->assertStringContainsString('Total a cobrar: $1.980', $order->mensaje_whatsapp);
        $this->assertStringNotContainsString('c/u', $order->mensaje_whatsapp);
    }

    public function test_a_plan_deactivated_before_checkout_is_dropped_and_the_order_is_still_created(): void
    {
        $product = $this->producto(1000);
        $plan = $this->plan();

        // Un admin lo desactiva desde el panel entre que el cliente lo eligió y
        // el submit del checkout. Al ser 100% informativo, el pedido NO se aborta:
        // simplemente se crea sin recargo, como un pedido en efectivo.
        $plan->update(['is_active' => false]);

        $response = $this->withSession([
            'cart' => [$product->id => 1],
            'cart_payment_plan' => ['id' => $plan->id],
        ])->postJson(route('cart.whatsapp'), [
            'customer_data' => $this->validCustomerData(),
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertNull($response->json('payment_plan'));

        $order = Order::first();

        $this->assertNotNull($order);
        $this->assertNull($order->card_payment_plan_id);
        $this->assertNull($order->payment_plan_name);
        $this->assertStringNotContainsString('Tarjeta de crédito', $order->mensaje_whatsapp);

        $this->assertNull(session('cart_payment_plan'));
    }
}
