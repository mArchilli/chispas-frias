<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DiscountCode;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CartDiscountCodeIntegrationTest extends TestCase
{
    use RefreshDatabase;

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

    private function crearProductoEnCarrito(int $cantidad = 5, float $precio = 100): array
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create(['price' => $precio, 'stock' => 50]);

        return [$product, $cantidad];
    }

    // --- applyDiscountCode: código válido --------------------------------------------

    public function test_aplicar_un_codigo_valido_lo_guarda_en_sesion_y_devuelve_el_monto(): void
    {
        [$product, $cantidad] = $this->crearProductoEnCarrito(5, 100);
        $this->crearCodigo(['code' => 'SUMMER10', 'percentage' => 10]);

        $response = $this->withSession(['cart' => [$product->id => $cantidad]])
            ->postJson(route('cart.discount.apply'), ['code' => 'summer10']);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'discountCode' => [
                'code' => 'SUMMER10',
                'percentage' => 10.0,
                'amount' => 50.0,
            ],
            'subtotal' => 500.0,
            'total' => 450.0,
        ]);

        $this->assertSame('SUMMER10', session('cart_discount_code'));
    }

    // --- applyDiscountCode: rechazos, uno por motivo ---------------------------------

    public function test_aplicar_un_codigo_inexistente_es_rechazado(): void
    {
        [$product, $cantidad] = $this->crearProductoEnCarrito();

        $response = $this->withSession(['cart' => [$product->id => $cantidad]])
            ->postJson(route('cart.discount.apply'), ['code' => 'NOEXISTE']);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
        $this->assertStringContainsString('no existe', $response->json('message'));
        $this->assertNull(session('cart_discount_code'));
    }

    public function test_aplicar_un_codigo_inactivo_es_rechazado(): void
    {
        [$product, $cantidad] = $this->crearProductoEnCarrito();
        $this->crearCodigo(['code' => 'INACTIVO', 'is_active' => false]);

        $response = $this->withSession(['cart' => [$product->id => $cantidad]])
            ->postJson(route('cart.discount.apply'), ['code' => 'INACTIVO']);

        $response->assertStatus(422);
        $this->assertStringContainsString('no está vigente', $response->json('message'));
        $this->assertNull(session('cart_discount_code'));
    }

    public function test_aplicar_un_codigo_fuera_de_rango_de_fechas_es_rechazado(): void
    {
        [$product, $cantidad] = $this->crearProductoEnCarrito();
        $this->crearCodigo([
            'code' => 'FUTURO',
            'start_date' => now()->addDays(5),
        ]);

        $response = $this->withSession(['cart' => [$product->id => $cantidad]])
            ->postJson(route('cart.discount.apply'), ['code' => 'FUTURO']);

        $response->assertStatus(422);
        $this->assertStringContainsString('no está vigente', $response->json('message'));
        $this->assertNull(session('cart_discount_code'));
    }

    public function test_aplicar_un_codigo_agotado_es_rechazado(): void
    {
        [$product, $cantidad] = $this->crearProductoEnCarrito();
        $this->crearCodigo(['code' => 'AGOTADO', 'usage_limit' => 3, 'usage_count' => 3]);

        $response = $this->withSession(['cart' => [$product->id => $cantidad]])
            ->postJson(route('cart.discount.apply'), ['code' => 'AGOTADO']);

        $response->assertStatus(422);
        $this->assertStringContainsString('límite de usos', $response->json('message'));
        $this->assertNull(session('cart_discount_code'));
    }

    public function test_aplicar_un_codigo_que_no_alcanza_el_minimo_de_compra_es_rechazado(): void
    {
        [$product, $cantidad] = $this->crearProductoEnCarrito(1, 50);
        $this->crearCodigo(['code' => 'MINIMO500', 'min_purchase_amount' => 500]);

        $response = $this->withSession(['cart' => [$product->id => $cantidad]])
            ->postJson(route('cart.discount.apply'), ['code' => 'MINIMO500']);

        $response->assertStatus(422);
        $this->assertStringContainsString('compra mínima', $response->json('message'));
        $this->assertNull(session('cart_discount_code'));
    }

    // --- removeDiscountCode ----------------------------------------------------------

    public function test_remover_el_codigo_lo_quita_de_sesion_y_del_carrito(): void
    {
        [$product, $cantidad] = $this->crearProductoEnCarrito(5, 100);
        $this->crearCodigo(['code' => 'SUMMER10']);

        $this->withSession(['cart' => [$product->id => $cantidad]])
            ->postJson(route('cart.discount.apply'), ['code' => 'SUMMER10'])
            ->assertOk();

        $this->assertSame('SUMMER10', session('cart_discount_code'));

        $removeResponse = $this->deleteJson(route('cart.discount.remove'));

        $removeResponse->assertOk();
        $removeResponse->assertJson(['success' => true]);
        $this->assertNull(session('cart_discount_code'));

        $indexResponse = $this->get(route('cart.index'));
        $indexResponse->assertInertia(fn (Assert $page) => $page
            ->where('discountCode', null)
            ->where('total', 500)
        );
    }

    // --- reflejo en props de GET /carrito y /carrito/checkout ------------------------

    public function test_el_codigo_aplicado_se_refleja_en_los_props_de_carrito_index(): void
    {
        [$product, $cantidad] = $this->crearProductoEnCarrito(5, 100);
        $this->crearCodigo(['code' => 'SUMMER10', 'percentage' => 10]);

        $this->withSession(['cart' => [$product->id => $cantidad]])
            ->postJson(route('cart.discount.apply'), ['code' => 'SUMMER10'])
            ->assertOk();

        $response = $this->get(route('cart.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Cart/Index')
            ->where('subtotal', 500)
            ->where('total', 450)
            ->where('discountCode.code', 'SUMMER10')
            ->where('discountCode.percentage', 10)
            ->where('discountCode.amount', 50)
            ->where('discountCodeRemovedReason', null)
        );
    }

    public function test_el_codigo_aplicado_se_refleja_en_los_props_de_checkout(): void
    {
        [$product, $cantidad] = $this->crearProductoEnCarrito(5, 100);
        $this->crearCodigo(['code' => 'SUMMER10', 'percentage' => 10]);

        $this->withSession(['cart' => [$product->id => $cantidad]])
            ->postJson(route('cart.discount.apply'), ['code' => 'SUMMER10'])
            ->assertOk();

        $response = $this->get(route('cart.checkout'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Cart/Checkout')
            ->where('subtotal', 500)
            ->where('total', 450)
            ->where('discountCode.code', 'SUMMER10')
            ->where('discountCode.percentage', 10)
            ->where('discountCode.amount', 50)
        );
    }

    // --- remoción automática cuando el código deja de ser válido ---------------------

    public function test_un_codigo_desactivado_despues_de_aplicarse_se_remueve_solo_al_recargar_el_carrito(): void
    {
        [$product, $cantidad] = $this->crearProductoEnCarrito(5, 100);
        $discountCode = $this->crearCodigo(['code' => 'SUMMER10', 'percentage' => 10]);

        $this->withSession(['cart' => [$product->id => $cantidad]])
            ->postJson(route('cart.discount.apply'), ['code' => 'SUMMER10'])
            ->assertOk();

        // El código deja de ser válido después de aplicado (p. ej. un admin lo
        // desactiva desde el panel) mientras sigue guardado en la sesión del carrito.
        $discountCode->update(['is_active' => false]);

        $response = $this->get(route('cart.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Cart/Index')
            ->where('discountCode', null)
            ->where('total', 500)
            ->where('discountCodeRemovedReason', fn ($reason) => str_contains($reason, 'no está vigente'))
        );

        $this->assertNull(session('cart_discount_code'));

        // Una segunda carga ya no debería reportar motivo: el código ya se quitó de
        // la sesión en la carga anterior, no queda nada que remover de nuevo.
        $second = $this->get(route('cart.index'));
        $second->assertInertia(fn (Assert $page) => $page
            ->where('discountCode', null)
            ->where('discountCodeRemovedReason', null)
        );
    }

    public function test_un_codigo_que_deja_de_alcanzar_el_minimo_al_bajar_el_subtotal_se_remueve_solo(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create(['price' => 100, 'stock' => 50]);
        $this->crearCodigo(['code' => 'MINIMO400', 'min_purchase_amount' => 400]);

        // Subtotal inicial de 500 alcanza el mínimo de 400.
        $this->withSession(['cart' => [$product->id => 5]])
            ->postJson(route('cart.discount.apply'), ['code' => 'MINIMO400'])
            ->assertOk();

        // Baja la cantidad en el carrito: el subtotal cae a 200, por debajo del mínimo.
        $this->patchJson(route('cart.update'), [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertOk();

        $response = $this->get(route('cart.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('discountCode', null)
            ->where('total', 200)
            ->where('discountCodeRemovedReason', fn ($reason) => str_contains($reason, 'compra mínima'))
        );

        $this->assertNull(session('cart_discount_code'));
    }
}
