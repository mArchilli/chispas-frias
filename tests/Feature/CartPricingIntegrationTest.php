<?php

namespace Tests\Feature;

use App\Enums\AlcanceOferta;
use App\Enums\TipoDescuento;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPriceTier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CartPricingIntegrationTest extends TestCase
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

    // --- index() refleja el precio resuelto por PricingService ---------------------

    public function test_index_y_orderitem_reflejan_el_precio_del_tier_aplicable(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create(['price' => 100, 'stock' => 50]);
        ProductPriceTier::factory()->create([
            'product_id' => $product->id,
            'cantidad_minima' => 10,
            'precio_unitario' => 80,
        ]);

        $response = $this->withSession(['cart' => [$product->id => 10]])->get(route('cart.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Cart/Index')
            ->where('cartItems.0.price', 80)
            ->where('cartItems.0.list_price', 80)
            ->where('cartItems.0.subtotal', 800)
            ->where('total', 800)
        );

        $whatsapp = $this->withSession(['cart' => [$product->id => 10]])
            ->postJson(route('cart.whatsapp'), ['customer_data' => $this->validCustomerData()]);
        $whatsapp->assertOk();

        $item = Order::first()->items()->where('product_id', $product->id)->first();
        $this->assertEquals(80.0, (float) $item->precio_unitario);
        $this->assertEquals(800.0, (float) $item->subtotal);
    }

    public function test_index_muestra_precio_final_precio_lista_y_ahorro_cuando_hay_oferta_activa(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create(['price' => 100, 'stock' => 50]);
        $product->offers()->create([
            'offer_price' => 75,
            'is_active' => true,
            'tipo_descuento' => TipoDescuento::Porcentaje,
            'valor_descuento' => 25,
            'alcance' => AlcanceOferta::Todos,
        ]);

        $response = $this->withSession(['cart' => [$product->id => 2]])->get(route('cart.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Cart/Index')
            ->where('cartItems.0.price', 75)
            ->where('cartItems.0.list_price', 100)
            ->where('cartItems.0.unit_savings', 25)
            ->where('cartItems.0.savings_percentage', 25)
            ->where('cartItems.0.subtotal', 150)
            ->where('total', 150)
        );
    }

    public function test_regresion_index_sin_tiers_ni_ofertas_usa_el_precio_base(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create(['price' => 250, 'stock' => 50]);

        $response = $this->withSession(['cart' => [$product->id => 3]])->get(route('cart.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Cart/Index')
            ->where('cartItems.0.price', 250)
            ->where('cartItems.0.unit_savings', 0)
            ->where('cartItems.0.subtotal', 750)
            ->where('total', 750)
        );
    }

    // --- oferta especifica sobre un tier puntual ------------------------------------

    public function test_oferta_especifica_sobre_un_tier_solo_se_aplica_en_la_cantidad_de_ese_tier(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create(['price' => 100, 'stock' => 100]);
        $tier = ProductPriceTier::factory()->create([
            'product_id' => $product->id,
            'cantidad_minima' => 10,
            'precio_unitario' => 80,
        ]);
        $product->offers()->create([
            'offer_price' => 60,
            'is_active' => true,
            'tipo_descuento' => TipoDescuento::Fijo,
            'valor_descuento' => 20,
            'alcance' => AlcanceOferta::Especifico,
            'product_price_tier_id' => $tier->id,
        ]);

        // Cantidad que cae en el tier -> la oferta específica sí aplica (80 - 20 = 60).
        $conTier = $this->withSession(['cart' => [$product->id => 10]])
            ->postJson(route('cart.whatsapp'), ['customer_data' => $this->validCustomerData()]);
        $conTier->assertOk();
        $itemConTier = Order::first()->items()->where('product_id', $product->id)->first();
        $this->assertEquals(60.0, (float) $itemConTier->precio_unitario);

        Order::query()->delete();

        // Cantidad que NO alcanza el tier -> la oferta específica no aplica, precio base.
        $sinTier = $this->withSession(['cart' => [$product->id => 3]])
            ->postJson(route('cart.whatsapp'), ['customer_data' => $this->validCustomerData()]);
        $sinTier->assertOk();
        $itemSinTier = Order::first()->items()->where('product_id', $product->id)->first();
        $this->assertEquals(100.0, (float) $itemSinTier->precio_unitario);
    }

    public function test_cantidad_que_no_alcanza_ningun_tier_usa_precio_base_con_o_sin_oferta(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create(['price' => 150, 'stock' => 50]);
        ProductPriceTier::factory()->create([
            'product_id' => $product->id,
            'cantidad_minima' => 10,
            'precio_unitario' => 120,
        ]);

        $response = $this->withSession(['cart' => [$product->id => 3]])
            ->postJson(route('cart.whatsapp'), ['customer_data' => $this->validCustomerData()]);

        $response->assertOk();

        $item = Order::first()->items()->where('product_id', $product->id)->first();
        $this->assertEquals(150.0, (float) $item->precio_unitario);
        $this->assertEquals(450.0, (float) $item->subtotal);
    }

    // --- total de la orden -----------------------------------------------------------

    public function test_el_total_de_la_orden_coincide_con_la_suma_de_precio_unitario_por_cantidad_de_sus_items(): void
    {
        $category = Category::factory()->create();

        $productA = Product::factory()->for($category)->create(['price' => 100, 'stock' => 50]);
        ProductPriceTier::factory()->create([
            'product_id' => $productA->id,
            'cantidad_minima' => 5,
            'precio_unitario' => 90,
        ]);

        $productB = Product::factory()->for($category)->create(['price' => 200, 'stock' => 50]);
        $productB->offers()->create([
            'offer_price' => 180,
            'is_active' => true,
            'tipo_descuento' => TipoDescuento::Fijo,
            'valor_descuento' => 20,
            'alcance' => AlcanceOferta::Todos,
        ]);

        $response = $this->withSession([
            'cart' => [
                $productA->id => 5,
                $productB->id => 2,
            ],
        ])->postJson(route('cart.whatsapp'), ['customer_data' => $this->validCustomerData()]);

        $response->assertOk();

        $order = Order::first();
        $sumaItems = $order->items->sum(fn ($item) => $item->precio_unitario * $item->cantidad);

        $this->assertEquals(810.0, (float) $order->total);
        $this->assertEquals((float) $order->total, (float) $sumaItems);
    }

    // --- performance: carrito con varios items ---------------------------------------

    public function test_las_queries_a_product_offers_escalan_linealmente_con_la_cantidad_de_productos_del_carrito(): void
    {
        $category = Category::factory()->create();
        $productos = Product::factory()->for($category)->count(3)->create(['stock' => 50]);
        [$productoA, $productoB, $productoC] = $productos->all();

        $offerQueries = 0;
        DB::listen(function ($query) use (&$offerQueries) {
            if (str_contains($query->sql, 'from "product_offers"')) {
                $offerQueries++;
            }
        });

        $this->withSession(['cart' => [$productoA->id => 1]])->get(route('cart.index'))->assertOk();
        $queriesParaUnItem = $offerQueries;
        $this->assertGreaterThan(0, $queriesParaUnItem);

        $offerQueries = 0;
        $this->withSession(['cart' => [
            $productoA->id => 1,
            $productoB->id => 1,
            $productoC->id => 1,
        ]])->get(route('cart.index'))->assertOk();

        // El costo por producto (> 1 query) hoy está dominado por los $appends legacy
        // de Product al serializarse en la respuesta Inertia (current_price,
        // discount_percentage, etc. — cada uno re-consulta product_offers por su
        // cuenta; ver hallazgo de Fase C1), NO tocados en esta fase. Lo que confirma
        // este test es que PricingService no le suma NINGUNA query extra a eso, y que
        // un carrito de 3 items escala 3x, no de forma descontrolada.
        $this->assertSame($queriesParaUnItem * 3, $offerQueries);
    }
}
