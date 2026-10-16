<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase B6, punto 7: CartController::add() y update() deben rechazar (422, sin
 * truncar en silencio) cualquier intento de pedir más cantidad de la que hay
 * en products.stock, con un mensaje que el frontend pueda mostrar.
 */
class CartStockEnforcementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * `line_key` de la primera (y única) línea del carrito de sesión. Los
     * endpoints update/remove operan por `line_key`, que el backend genera a
     * partir del contenido de la línea; el test lo lee de los props de GET /carrito.
     */
    private function lineKeyDelCarrito(): string
    {
        return $this->get(route('cart.index'))
            ->viewData('page')['props']['cartItems'][0]['line_key'];
    }

    public function test_add_rechaza_una_cantidad_mayor_al_stock_disponible(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create(['stock' => 3]);

        $response = $this->postJson(route('cart.add'), [
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
        $this->assertNotEmpty($response->json('message'));
    }

    public function test_add_rechaza_cuando_lo_ya_agregado_mas_lo_nuevo_supera_el_stock(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create(['stock' => 3]);

        $response = $this->withSession(['cart' => [$product->id => 2]])
            ->postJson(route('cart.add'), [
                'product_id' => $product->id,
                'quantity' => 2,
            ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
        $this->assertNotEmpty($response->json('message'));
    }

    public function test_add_acepta_una_cantidad_igual_al_stock_disponible(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create(['stock' => 3]);

        $response = $this->postJson(route('cart.add'), [
            'product_id' => $product->id,
            'quantity' => 3,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
    }

    public function test_update_rechaza_una_cantidad_mayor_al_stock_disponible(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create(['stock' => 3]);

        $this->withSession(['cart' => [$product->id => 1]]);

        $response = $this->patchJson(route('cart.update'), [
            'line_key' => $this->lineKeyDelCarrito(),
            'quantity' => 10,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
        $this->assertNotEmpty($response->json('message'));
    }

    public function test_update_acepta_una_cantidad_igual_al_stock_disponible(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create(['stock' => 3]);

        $this->withSession(['cart' => [$product->id => 1]]);

        $response = $this->patchJson(route('cart.update'), [
            'line_key' => $this->lineKeyDelCarrito(),
            'quantity' => 3,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
    }

    public function test_add_permite_mas_de_99_unidades_hasta_el_stock_disponible(): void
    {
        $product = Product::factory()->for(Category::factory())->create(['stock' => 1000]);

        $this->postJson(route('cart.add'), ['product_id' => $product->id, 'quantity' => 1000])
            ->assertOk()
            ->assertJson(['success' => true, 'cartCount' => 1000]);

        $this->assertSame(1000, session('cart')[0]['quantity']);
    }

    public function test_add_de_mas_de_99_unidades_sigue_rechazando_el_exceso_de_stock(): void
    {
        $product = Product::factory()->for(Category::factory())->create(['stock' => 100]);

        $this->postJson(route('cart.add'), ['product_id' => $product->id, 'quantity' => 101])
            ->assertUnprocessable()
            ->assertJson(['success' => false]);

        $this->assertEmpty(session('cart', []));
    }

    public function test_add_sigue_validando_el_stock_acumulado_con_cantidades_mayores_a_99(): void
    {
        $product = Product::factory()->for(Category::factory())->create(['stock' => 150]);

        $this->postJson(route('cart.add'), ['product_id' => $product->id, 'quantity' => 100])->assertOk();
        $this->postJson(route('cart.add'), ['product_id' => $product->id, 'quantity' => 100])
            ->assertUnprocessable()
            ->assertJson(['success' => false]);

        $this->assertSame(100, session('cart')[0]['quantity']);
    }

    public function test_update_de_mas_de_99_unidades_sigue_rechazando_el_exceso_de_stock(): void
    {
        $product = Product::factory()->for(Category::factory())->create(['stock' => 250]);
        $this->postJson(route('cart.add'), ['product_id' => $product->id, 'quantity' => 100])->assertOk();

        $this->patchJson(route('cart.update'), [
            'line_key' => session('cart')[0]['line_key'], 'quantity' => 251,
        ])->assertUnprocessable()->assertJson(['success' => false]);

        $this->assertSame(100, session('cart')[0]['quantity']);
    }

    public function test_mas_de_99_unidades_respeta_el_stock_de_la_variante(): void
    {
        $product = Product::factory()->for(Category::factory())->create(['stock' => 5000]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'stock' => 120]);

        $this->postJson(route('cart.add'), [
            'product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 100,
        ])->assertOk();

        $lineKey = session('cart')[0]['line_key'];
        $this->patchJson(route('cart.update'), ['line_key' => $lineKey, 'quantity' => 120])
            ->assertOk()->assertJson(['cartCount' => 120]);
        $this->patchJson(route('cart.update'), ['line_key' => $lineKey, 'quantity' => 121])
            ->assertUnprocessable()->assertJson(['success' => false]);

        $this->assertSame(120, session('cart')[0]['quantity']);
    }

    public function test_update_conserva_el_limite_existente_de_los_combos(): void
    {
        $product = Product::factory()->for(Category::factory())->create(['stock' => 500]);
        $combo = Combo::create(['title' => 'Combo de prueba', 'price' => 1000, 'is_active' => true]);
        $combo->items()->create(['product_id' => $product->id, 'quantity' => 1]);

        $this->postJson(route('cart.combo.add'), ['combo_id' => $combo->id, 'quantity' => 99])->assertOk();
        $this->patchJson(route('cart.update'), [
            'line_key' => session('cart')[0]['line_key'], 'quantity' => 100,
        ])->assertUnprocessable()->assertJsonValidationErrors('quantity');

        $this->assertSame(99, session('cart')[0]['quantity']);
    }
}
