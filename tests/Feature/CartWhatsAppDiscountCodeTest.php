<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Flujo completo de checkout (POST /carrito/whatsapp) con un código de
 * descuento aplicado, en el mismo espíritu que CartWhatsAppOrderTest para
 * stock: la orden debe reflejar el descuento con exactitud, y si el código se
 * agota justo antes de confirmarse (carrera con otro checkout concurrente) la
 * orden no debe crearse en absoluto — todo o nada, igual que con stock
 * insuficiente.
 */
class CartWhatsAppDiscountCodeTest extends TestCase
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

    private function crearCodigo(array $overrides = []): DiscountCode
    {
        return DiscountCode::create(array_merge([
            'code' => 'CODE' . random_int(100000, 999999),
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

    public function test_crea_la_orden_con_el_codigo_de_descuento_aplicado_y_registra_el_uso(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create(['title' => 'Chispita', 'price' => 1000, 'stock' => 50]);
        $discountCode = $this->crearCodigo(['code' => 'SUMMER10', 'percentage' => 10, 'usage_count' => 0]);

        $response = $this->withSession([
            'cart' => [$product->id => 2],
            'cart_discount_code' => 'SUMMER10',
        ])->postJson(route('cart.whatsapp'), [
            'customer_data' => $this->validCustomerData(),
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'subtotal' => 2000.0,
            'discount_amount' => 200.0,
            'total' => 1800.0,
        ]);

        $this->assertSame(1, Order::count());
        $order = Order::first();

        $this->assertSame($discountCode->id, $order->discount_code_id);
        $this->assertSame('SUMMER10', $order->discount_code);
        $this->assertEquals(2000.0, (float) $order->subtotal);
        $this->assertEquals(200.0, (float) $order->discount_amount);
        $this->assertEquals(1800.0, (float) $order->total);

        $discountCode->refresh();
        $this->assertSame(1, $discountCode->usage_count);

        $this->assertStringContainsString('Código de descuento: SUMMER10', $order->mensaje_whatsapp);

        $this->assertNull(session('cart'));
        $this->assertNull(session('cart_discount_code'));
    }

    public function test_crea_la_orden_sin_codigo_si_no_hay_ninguno_en_sesion(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create(['price' => 1000, 'stock' => 50]);

        $response = $this->withSession([
            'cart' => [$product->id => 1],
        ])->postJson(route('cart.whatsapp'), [
            'customer_data' => $this->validCustomerData(),
        ]);

        $response->assertOk();

        $order = Order::first();
        $this->assertNull($order->discount_code_id);
        $this->assertNull($order->discount_code);
        $this->assertEquals(0.0, (float) $order->discount_amount);
        $this->assertEquals(1000.0, (float) $order->total);
    }

    public function test_no_crea_la_orden_si_el_codigo_se_agota_por_una_carrera_justo_antes_de_confirmar(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create(['title' => 'Chispita', 'price' => 1000, 'stock' => 50]);
        $discountCode = $this->crearCodigo(['code' => 'UNICO', 'usage_limit' => 1, 'usage_count' => 0]);

        // Simula una condición de carrera real: justo después de que el chequeo
        // optimista de buscarValido() lee usage_count=0 (dentro del límite), otro
        // checkout concurrente consume el único uso disponible, antes de que
        // registrarUso() tome el lock definitivo dentro de la transacción.
        $queryCount = 0;
        DB::listen(function ($query) use ($discountCode, &$queryCount) {
            if (! str_contains($query->sql, 'from "discount_codes"')) {
                return;
            }

            $queryCount++;

            // 1ra lectura: buscarValido(), el chequeo optimista fuera de la transacción.
            if ($queryCount === 1) {
                DB::table('discount_codes')->where('id', $discountCode->id)->update(['usage_count' => 1]);
            }
        });

        $response = $this->withSession([
            'cart' => [$product->id => 2],
            'cart_discount_code' => 'UNICO',
        ])->postJson(route('cart.whatsapp'), [
            'customer_data' => $this->validCustomerData(),
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
        $this->assertStringContainsString('límite de usos', $response->json('message'));

        $this->assertSame(0, Order::count());
        $this->assertSame(0, OrderItem::count());
        $this->assertSame(0, StockMovement::count());

        // El stock no debe quedar descontado: la transacción completa se revirtió.
        $product->refresh();
        $this->assertSame(50, $product->stock);

        // usage_count sigue en 1 (el de la carrera simulada), no en 2: la orden que
        // perdió la carrera no debe haber incrementado el contador.
        $discountCode->refresh();
        $this->assertSame(1, $discountCode->usage_count);

        $this->assertNull(session('cart_discount_code'));
    }
}
