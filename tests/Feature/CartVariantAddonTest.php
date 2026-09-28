<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Carrito de sesión como array de líneas: dos líneas del mismo producto con
 * distinto color o distintos add-ons conviven sin sumarse; sólo suman cantidad
 * cuando coinciden producto + variante + add-ons + textos (mismo `line_key`).
 * Un producto sin opciones se comporta exactamente igual que antes.
 */
class CartVariantAddonTest extends TestCase
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
            'observations' => null,
        ], $overrides);
    }

    private function producto(array $overrides = []): Product
    {
        $category = Category::factory()->create();

        return Product::factory()->for($category)->create(array_merge([
            'price' => 1000,
            'stock' => 50,
            'is_active' => true,
        ], $overrides));
    }

    /** @return array<int, array<string, mixed>> */
    private function cartItems(): array
    {
        return $this->get(route('cart.index'))
            ->viewData('page')['props']['cartItems'];
    }

    // --- regresión: producto sin opciones ---------------------------------------

    public function test_un_producto_sin_variante_ni_addons_suma_cantidad_en_una_sola_linea(): void
    {
        $product = $this->producto(['stock' => 10]);

        $this->postJson(route('cart.add'), ['product_id' => $product->id, 'quantity' => 2])->assertOk();
        $this->postJson(route('cart.add'), ['product_id' => $product->id, 'quantity' => 3])->assertOk();

        $items = $this->cartItems();

        $this->assertCount(1, $items);
        $this->assertSame(5, $items[0]['quantity']);
        $this->assertNull($items[0]['variant']);
        $this->assertSame([], $items[0]['addons']);
    }

    // --- líneas por variante ---------------------------------------------------

    public function test_agregar_el_mismo_producto_con_dos_colores_crea_dos_lineas(): void
    {
        $product = $this->producto();
        $rojo = ProductVariant::factory()->create(['product_id' => $product->id, 'name' => 'Rojo', 'price_addon' => 200, 'stock' => 20]);
        $azul = ProductVariant::factory()->create(['product_id' => $product->id, 'name' => 'Azul', 'price_addon' => 0, 'stock' => 20]);

        $this->postJson(route('cart.add'), ['product_id' => $product->id, 'quantity' => 2, 'variant_id' => $rojo->id])->assertOk();
        $this->postJson(route('cart.add'), ['product_id' => $product->id, 'quantity' => 1, 'variant_id' => $azul->id])->assertOk();

        $items = collect($this->cartItems())->keyBy(fn ($i) => $i['variant']['name']);

        $this->assertCount(2, $items);
        $this->assertSame(2, $items['Rojo']['quantity']);
        $this->assertSame(1, $items['Azul']['quantity']);
    }

    public function test_agregar_dos_veces_el_mismo_color_suma_la_cantidad(): void
    {
        $product = $this->producto();
        $rojo = ProductVariant::factory()->create(['product_id' => $product->id, 'name' => 'Rojo', 'stock' => 20]);

        $this->postJson(route('cart.add'), ['product_id' => $product->id, 'quantity' => 2, 'variant_id' => $rojo->id])->assertOk();
        $this->postJson(route('cart.add'), ['product_id' => $product->id, 'quantity' => 3, 'variant_id' => $rojo->id])->assertOk();

        $items = $this->cartItems();

        $this->assertCount(1, $items);
        $this->assertSame(5, $items[0]['quantity']);
    }

    public function test_el_precio_de_la_linea_incluye_el_recargo_de_la_variante(): void
    {
        $product = $this->producto(['price' => 1000]);
        $rojo = ProductVariant::factory()->create(['product_id' => $product->id, 'name' => 'Rojo', 'price_addon' => 250, 'stock' => 20]);

        $this->postJson(route('cart.add'), ['product_id' => $product->id, 'quantity' => 2, 'variant_id' => $rojo->id])->assertOk();

        $item = $this->cartItems()[0];

        $this->assertSame(1000.0, $item['price']);            // base, sin opciones
        $this->assertSame(250.0, $item['variant_surcharge']);
        $this->assertSame(1250.0, $item['unit_price']);       // base + recargo
        $this->assertSame(2500.0, $item['subtotal']);         // 2 × 1250
    }

    // --- líneas por add-ons y por texto ---------------------------------------

    public function test_el_precio_de_la_linea_incluye_el_total_de_addons(): void
    {
        $product = $this->producto(['price' => 1000]);
        $addon = Addon::factory()->create(['price' => 300]);
        $product->addons()->attach($addon->id, ['price_override' => null]);

        $this->postJson(route('cart.add'), [
            'product_id' => $product->id,
            'quantity' => 1,
            'addon_ids' => [$addon->id],
        ])->assertOk();

        $item = $this->cartItems()[0];

        $this->assertSame(300.0, $item['addons_total']);
        $this->assertSame(1300.0, $item['unit_price']);
        $this->assertSame(1300.0, $item['subtotal']);
        $this->assertCount(1, $item['addons']);
        $this->assertSame($addon->id, $item['addons'][0]['addon_id']);
    }

    public function test_el_mismo_addon_con_distinto_texto_crea_dos_lineas(): void
    {
        $product = $this->producto();
        $grabado = Addon::factory()->conTexto()->create(['price' => 100, 'max_characters' => 40]);
        $product->addons()->attach($grabado->id);

        $this->postJson(route('cart.add'), [
            'product_id' => $product->id,
            'quantity' => 1,
            'addon_ids' => [$grabado->id],
            'addon_texts' => [$grabado->id => 'Ana'],
        ])->assertOk();

        $this->postJson(route('cart.add'), [
            'product_id' => $product->id,
            'quantity' => 1,
            'addon_ids' => [$grabado->id],
            'addon_texts' => [$grabado->id => 'Beto'],
        ])->assertOk();

        $items = collect($this->cartItems());

        $this->assertCount(2, $items);
        $this->assertEqualsCanonicalizing(
            ['Ana', 'Beto'],
            $items->map(fn ($i) => $i['addons'][0]['custom_text'])->all(),
        );
    }

    // --- validación server-side ----------------------------------------------

    public function test_rechaza_una_variante_que_no_pertenece_al_producto(): void
    {
        $product = $this->producto();
        ProductVariant::factory()->create(['product_id' => $product->id, 'stock' => 5]);
        $ajena = ProductVariant::factory()->create(['stock' => 5]); // de otro producto

        $response = $this->postJson(route('cart.add'), [
            'product_id' => $product->id,
            'quantity' => 1,
            'variant_id' => $ajena->id,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
        $this->assertSame([], $this->cartItems());
    }

    public function test_rechaza_agregar_un_producto_con_variantes_sin_elegir_color(): void
    {
        $product = $this->producto();
        ProductVariant::factory()->create(['product_id' => $product->id, 'stock' => 5]);

        $response = $this->postJson(route('cart.add'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }

    public function test_rechaza_un_addon_con_texto_obligatorio_sin_texto(): void
    {
        $product = $this->producto();
        $grabado = Addon::factory()->conTexto()->create(['price' => 100]);
        $product->addons()->attach($grabado->id);

        $response = $this->postJson(route('cart.add'), [
            'product_id' => $product->id,
            'quantity' => 1,
            'addon_ids' => [$grabado->id],
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }

    public function test_variante_a_eleccion_del_cliente_exige_color_libre(): void
    {
        $product = $this->producto();
        $libre = ProductVariant::factory()->customColor()->create([
            'product_id' => $product->id,
            'name' => 'A elección',
            'stock' => null,
        ]);

        $this->postJson(route('cart.add'), [
            'product_id' => $product->id,
            'quantity' => 1,
            'variant_id' => $libre->id,
        ])->assertStatus(422);

        $this->postJson(route('cart.add'), [
            'product_id' => $product->id,
            'quantity' => 1,
            'variant_id' => $libre->id,
            'custom_color_text' => 'Violeta con destellos',
        ])->assertOk();

        $item = $this->cartItems()[0];
        $this->assertSame('Violeta con destellos', $item['custom_color_text']);
    }

    public function test_add_rechaza_una_cantidad_mayor_al_stock_de_la_variante(): void
    {
        $product = $this->producto(['stock' => 100]);
        $rojo = ProductVariant::factory()->create(['product_id' => $product->id, 'stock' => 3]);

        $response = $this->postJson(route('cart.add'), [
            'product_id' => $product->id,
            'quantity' => 5,
            'variant_id' => $rojo->id,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }

    // --- update / remove operan por line_key --------------------------------

    public function test_update_y_remove_operan_sobre_la_linea_indicada_sin_tocar_las_demas(): void
    {
        $product = $this->producto();
        $rojo = ProductVariant::factory()->create(['product_id' => $product->id, 'name' => 'Rojo', 'stock' => 20]);
        $azul = ProductVariant::factory()->create(['product_id' => $product->id, 'name' => 'Azul', 'stock' => 20]);

        $this->postJson(route('cart.add'), ['product_id' => $product->id, 'quantity' => 1, 'variant_id' => $rojo->id])->assertOk();
        $this->postJson(route('cart.add'), ['product_id' => $product->id, 'quantity' => 1, 'variant_id' => $azul->id])->assertOk();

        $items = collect($this->cartItems())->keyBy(fn ($i) => $i['variant']['name']);
        $rojaKey = $items['Rojo']['line_key'];
        $azulKey = $items['Azul']['line_key'];

        $this->patchJson(route('cart.update'), ['line_key' => $rojaKey, 'quantity' => 7])->assertOk();

        $items = collect($this->cartItems())->keyBy(fn ($i) => $i['variant']['name']);
        $this->assertSame(7, $items['Rojo']['quantity']);
        $this->assertSame(1, $items['Azul']['quantity']);

        $this->deleteJson(route('cart.remove'), ['line_key' => $rojaKey])->assertOk();

        $items = $this->cartItems();
        $this->assertCount(1, $items);
        $this->assertSame($azulKey, $items[0]['line_key']);
    }

    // --- checkout: snapshot en order_items + stock de variante --------------

    public function test_el_checkout_snapshotea_variante_y_addons_en_el_order_item_y_descuenta_stock_de_la_variante(): void
    {
        $product = $this->producto(['price' => 1000, 'stock' => 100]);
        $rojo = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Rojo',
            'color_hex' => '#ff0000',
            'price_addon' => 200,
            'stock' => 10,
        ]);
        $grabado = Addon::factory()->conTexto()->create(['name' => 'Grabado láser', 'price' => 300, 'max_characters' => 40]);
        $product->addons()->attach($grabado->id, ['price_override' => 150]);

        $this->postJson(route('cart.add'), [
            'product_id' => $product->id,
            'quantity' => 2,
            'variant_id' => $rojo->id,
            'addon_ids' => [$grabado->id],
            'addon_texts' => [$grabado->id => 'Feliz cumple'],
        ])->assertOk();

        $response = $this->postJson(route('cart.whatsapp'), [
            'customer_data' => $this->validCustomerData(),
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        // unit_price = 1000 (base) + 200 (color) + 150 (addon override) = 1350
        $order = Order::first();
        $this->assertEquals(2700.0, (float) $order->total);

        $item = $order->items()->first();
        $this->assertSame($rojo->id, $item->product_variant_id);
        $this->assertSame('Rojo', $item->variant_name);
        $this->assertSame('#ff0000', $item->variant_color_hex);
        $this->assertEquals(200.0, (float) $item->variant_price_addon);
        $this->assertEquals(150.0, (float) $item->addons_total);
        $this->assertEquals(1350.0, (float) $item->precio_unitario);
        $this->assertEquals(1000.0, (float) $item->base_unit_price);
        $this->assertEquals(2700.0, (float) $item->subtotal);

        $this->assertIsArray($item->addons_selected);
        $this->assertCount(1, $item->addons_selected);
        $this->assertSame($grabado->id, $item->addons_selected[0]['addon_id']);
        $this->assertSame('Grabado láser', $item->addons_selected[0]['name']);
        $this->assertEquals(150.0, (float) $item->addons_selected[0]['price']);
        $this->assertSame('Feliz cumple', $item->addons_selected[0]['custom_text']);

        // Stock: descuenta de la variante, no del producto.
        $rojo->refresh();
        $product->refresh();
        $this->assertSame(8, $rojo->stock);
        $this->assertSame(100, $product->stock);

        $movimiento = StockMovement::where('order_id', $order->id)->first();
        $this->assertSame($rojo->id, $movimiento->product_variant_id);
        $this->assertSame(-2, $movimiento->cantidad);

        $this->assertStringContainsString('Color: Rojo', $order->mensaje_whatsapp);
        $this->assertStringContainsString('Grabado láser', $order->mensaje_whatsapp);
        $this->assertStringContainsString('Feliz cumple', $order->mensaje_whatsapp);
    }

    // --- checkout: defensa en profundidad de opciones obligatorias ----------

    /**
     * Inyecta una línea cruda en la sesión (sin pasar por add(), que la
     * bloquearía): simula opciones que quedaron incompletas o dejaron de ser
     * válidas después de armar el carrito.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function sessionCartLine(int $productId, array $overrides = []): array
    {
        return array_merge([
            'product_id' => $productId,
            'quantity' => 1,
            'variant_id' => null,
            'addon_selections' => [],
            'custom_color_text' => null,
        ], $overrides);
    }

    public function test_el_checkout_aborta_si_una_linea_no_eligio_color_y_nombra_el_producto(): void
    {
        $product = $this->producto(['title' => 'Vela bengala']);
        ProductVariant::factory()->create(['product_id' => $product->id, 'name' => 'Rojo', 'stock' => 10]);

        $response = $this->withSession([
            'cart' => [$this->sessionCartLine($product->id)],
        ])->postJson(route('cart.whatsapp'), [
            'customer_data' => $this->validCustomerData(),
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
        $this->assertStringContainsString('Vela bengala', $response->json('message'));
        $this->assertSame(0, Order::count());
        $this->assertSame(0, StockMovement::count());
    }

    public function test_el_checkout_aborta_si_la_variante_a_eleccion_no_trae_color_libre(): void
    {
        $product = $this->producto(['title' => 'Cañón papel']);
        $libre = ProductVariant::factory()->customColor()->create([
            'product_id' => $product->id,
            'name' => 'A elección',
            'stock' => null,
        ]);

        $response = $this->withSession([
            'cart' => [$this->sessionCartLine($product->id, [
                'variant_id' => $libre->id,
                'custom_color_text' => '   ',
            ])],
        ])->postJson(route('cart.whatsapp'), [
            'customer_data' => $this->validCustomerData(),
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
        $this->assertStringContainsString('Cañón papel', $response->json('message'));
        $this->assertSame(0, Order::count());
    }

    public function test_el_checkout_aborta_si_un_addon_con_texto_obligatorio_llego_sin_texto(): void
    {
        $product = $this->producto(['title' => 'Torta luminosa']);
        $grabado = Addon::factory()->conTexto()->create(['name' => 'Grabado láser']);
        $product->addons()->attach($grabado->id);

        $response = $this->withSession([
            'cart' => [$this->sessionCartLine($product->id, [
                'addon_selections' => [['addon_id' => $grabado->id, 'custom_text' => null]],
            ])],
        ])->postJson(route('cart.whatsapp'), [
            'customer_data' => $this->validCustomerData(),
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
        $this->assertStringContainsString('Grabado láser', $response->json('message'));
        $this->assertSame(0, Order::count());
    }

    public function test_el_checkout_aborta_si_la_variante_de_una_linea_se_desactivo(): void
    {
        $product = $this->producto();
        $rojo = ProductVariant::factory()->create(['product_id' => $product->id, 'name' => 'Rojo', 'stock' => 10]);

        $this->postJson(route('cart.add'), [
            'product_id' => $product->id,
            'quantity' => 1,
            'variant_id' => $rojo->id,
        ])->assertOk();

        $rojo->update(['is_active' => false]);

        $response = $this->postJson(route('cart.whatsapp'), [
            'customer_data' => $this->validCustomerData(),
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
        $this->assertSame(0, Order::count());
        $this->assertSame(0, StockMovement::count());
    }

    public function test_el_mensaje_de_whatsapp_usa_color_solicitado_para_la_variante_a_eleccion(): void
    {
        $product = $this->producto(['title' => 'Vela bengala']);
        $libre = ProductVariant::factory()->customColor()->create([
            'product_id' => $product->id,
            'name' => 'A elección',
            'price_addon' => 0,
            'stock' => null,
        ]);
        $grabado = Addon::factory()->conTexto()->create(['name' => 'Grabado láser', 'price' => 150]);
        $product->addons()->attach($grabado->id);

        $this->postJson(route('cart.add'), [
            'product_id' => $product->id,
            'quantity' => 1,
            'variant_id' => $libre->id,
            'custom_color_text' => 'Violeta con destellos',
            'addon_ids' => [$grabado->id],
            'addon_texts' => [$grabado->id => 'Feliz cumple'],
        ])->assertOk();

        $this->postJson(route('cart.whatsapp'), [
            'customer_data' => $this->validCustomerData(),
        ])->assertOk();

        $mensaje = Order::first()->mensaje_whatsapp;

        $this->assertStringContainsString('Color solicitado: Violeta con destellos', $mensaje);
        $this->assertStringNotContainsString('Color: A elección', $mensaje);
        $this->assertStringContainsString('Grabado láser: "Feliz cumple" (+$150)', $mensaje);
    }

    public function test_el_checkout_rechaza_todo_el_carrito_si_la_variante_no_tiene_stock(): void
    {
        $product = $this->producto(['stock' => 100]);
        $rojo = ProductVariant::factory()->create(['product_id' => $product->id, 'name' => 'Rojo', 'stock' => 1]);

        // Cae en sesión sin pasar por add() (que lo bloquearía): simula el color
        // agotándose después de agregarlo al carrito.
        $this->withSession(['cart' => [
            [
                'product_id' => $product->id,
                'quantity' => 5,
                'variant_id' => $rojo->id,
                'addon_selections' => [],
                'custom_color_text' => null,
            ],
        ]]);

        $response = $this->postJson(route('cart.whatsapp'), [
            'customer_data' => $this->validCustomerData(),
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
        $response->assertJsonPath('stock_insuficiente.0.product_id', $product->id);
        $response->assertJsonPath('stock_insuficiente.0.stock_disponible', 1);

        $this->assertSame(0, Order::count());
    }
}
