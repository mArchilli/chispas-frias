<?php

namespace Tests\Feature\Rukoz;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPriceTier;
use App\Models\ProductVariant;
use App\Rukoz\EspejoStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Rukoz\CoreClient\Eventos\StockActualizado;
use Tests\Support\CentralDePrueba;
use Tests\TestCase;

/**
 * CHK-2c2 — Lo que viaja al Central es lo que el cliente vio: el escalón por producto (suma de sus líneas) ya está en el
 * `precio_unitario` de cada línea y en el `total` de la reserva.
 */
class EscalasPorProductoCentralTest extends TestCase
{
    use RefreshDatabase;

    private CentralDePrueba $central;

    protected function setUp(): void
    {
        parent::setUp();

        $this->central = CentralDePrueba::instalar();
        Event::listen(StockActualizado::class, [EspejoStock::class, 'alActualizarse']);
    }

    protected function tearDown(): void
    {
        $this->assertSame([], $this->central->erroresDeContrato, 'La web mandó un body que el Central rechazaría');

        parent::tearDown();
    }

    public function test_el_payload_al_central_lleva_el_precio_del_escalon_por_producto_y_el_total_mostrado(): void
    {
        $producto = Product::factory()->for(Category::factory())->create(['title' => 'Chispa', 'price' => 100, 'stock' => 500, 'is_active' => true]);
        ProductPriceTier::factory()->create(['product_id' => $producto->id, 'cantidad_minima' => 20, 'precio_unitario' => 80]);
        $roja = ProductVariant::factory()->create(['product_id' => $producto->id, 'name' => 'Roja', 'price_addon' => 0, 'stock' => 500, 'is_active' => true]);
        $azul = ProductVariant::factory()->create(['product_id' => $producto->id, 'name' => 'Azul', 'price_addon' => 0, 'stock' => 500, 'is_active' => true]);
        $carrito = [
            ['product_id' => $producto->id, 'quantity' => 10, 'variant_id' => $roja->id],
            ['product_id' => $producto->id, 'quantity' => 10, 'variant_id' => $azul->id],
        ];

        $visto = $this->withSession(['cart' => $carrito])->getJson(route('cart.preview'))->json();
        $this->assertEquals(1600, $visto['subtotal']);

        $respuesta = $this->withSession(['cart' => $carrito])
            ->postJson(route('cart.whatsapp'), ['customer_data' => ['name' => 'Juana', 'phone' => '1122334455', 'province' => 'buenos-aires']])
            ->assertOk()
            ->assertJson(['success' => true, 'total' => 1600.0]);

        $this->assertCount(1, $this->central->pedidos);
        $payload = array_values($this->central->pedidos)[0]['payload'];

        $this->assertEquals($visto['subtotal'], $payload['total']);
        $this->assertCount(2, $payload['items']);

        foreach ($payload['items'] as $linea) {
            $this->assertSame('variante', $linea['tipo']);
            $this->assertSame(10, $linea['cantidad']);
            $this->assertEquals(80.0, $linea['precio_unitario']);
        }

        $this->assertEquals(1600, Order::findOrFail($respuesta->json('order_id'))->total);
    }
}
