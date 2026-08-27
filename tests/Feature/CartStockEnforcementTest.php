<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
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
}
