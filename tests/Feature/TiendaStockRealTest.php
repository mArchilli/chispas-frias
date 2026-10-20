<?php

namespace Tests\Feature;

use App\Exceptions\StockInsuficienteException;
use App\Models\Category;
use App\Models\Combo;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\StockService;
use App\Support\Stock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * 10.2 — Con el stock del Central las cantidades llegan en 0 o bajas (hasta ahora todo era ilimitado y esto casi no se
 * probó): «Sin stock» en el listado y la ficha, el carrito no pasa de lo disponible, el checkout rechaza nombrando el
 * producto y el color, y un combo con un componente en 0 no se vende. Un producto con variantes se juzga por sus variantes.
 */
class TiendaStockRealTest extends TestCase
{
    use RefreshDatabase;

    private function producto(array $attrs = []): Product
    {
        return Product::factory()->for(Category::factory())->create(array_merge([
            'price' => 1000, 'stock' => 50, 'is_active' => true,
        ], $attrs));
    }

    private function variante(Product $producto, string $nombre, ?int $stock, array $extra = []): ProductVariant
    {
        return ProductVariant::factory()->create(array_merge([
            'product_id' => $producto->id, 'name' => $nombre, 'price_addon' => 0, 'stock' => $stock, 'is_active' => true,
        ], $extra));
    }

    private function datosCliente(): array
    {
        return [
            'name' => 'Juana', 'lastname' => 'Pérez', 'dni' => '30111222', 'province' => 'buenos-aires', 'city' => 'La Plata',
            'postal_code' => '1900', 'phone' => '1122334455', 'email' => 'juana@example.com', 'observations' => null,
        ];
    }

    private function checkout(array $carrito): \Illuminate\Testing\TestResponse
    {
        return $this->withSession(['cart' => $carrito])
            ->postJson(route('cart.whatsapp'), ['customer_data' => $this->datosCliente()]);
    }

    private function lineaVariante(Product $p, ProductVariant $v, int $cantidad): array
    {
        return [
            'line_key' => "p{$p->id}-v{$v->id}", 'product_id' => $p->id, 'quantity' => $cantidad,
            'variant_id' => $v->id, 'addon_selections' => [], 'custom_color_text' => null,
        ];
    }

    // ---- La convención de «ilimitado» ----

    public function test_la_convencion_de_ilimitado_vive_en_una_sola_clase(): void
    {
        $this->assertSame(9999, Stock::ILIMITADO_PRODUCTO);
        $this->assertNull(Stock::ILIMITADO_VARIANTE);
        $this->assertSame(9999, Stock::ilimitadoDe('producto'));
        $this->assertNull(Stock::ilimitadoDe('variante'));

        $this->assertTrue(Stock::esIlimitado(null));
        $this->assertTrue(Stock::esIlimitado(9999));
        $this->assertFalse(Stock::esIlimitado(9998));
        $this->assertFalse(Stock::esIlimitado(0));

        // El default de la columna y del modelo es el mismo valor.
        $this->assertSame(Stock::ILIMITADO_PRODUCTO, (new Product)->stock);
    }

    // ---- inStock() / isInStock() miran las variantes ----

    public function test_un_producto_con_todas_sus_variantes_en_cero_esta_sin_stock_aunque_su_columna_diga_9999(): void
    {
        $p = $this->producto(['stock' => 9999]);
        $this->variante($p, 'Rosa', 0);
        $this->variante($p, 'Celeste', 0);

        $this->assertFalse($p->fresh()->isInStock());
        $this->assertFalse(Product::inStock()->whereKey($p->id)->exists());
        $this->assertTrue(Product::outOfStock()->whereKey($p->id)->exists());
    }

    public function test_alcanza_con_una_variante_activa_con_stock_o_ilimitada(): void
    {
        $conNumero = $this->producto(['stock' => 0]);
        $this->variante($conNumero, 'Rosa', 0);
        $this->variante($conNumero, 'Negra', 4);

        $ilimitada = $this->producto(['stock' => 0]);
        $this->variante($ilimitada, 'Otro color', null);

        foreach ([$conNumero, $ilimitada] as $p) {
            $this->assertTrue($p->fresh()->isInStock());
            $this->assertTrue(Product::inStock()->whereKey($p->id)->exists());
            $this->assertFalse(Product::outOfStock()->whereKey($p->id)->exists());
        }
    }

    public function test_una_variante_inactiva_con_stock_no_cuenta(): void
    {
        $p = $this->producto(['stock' => 9999]);
        $this->variante($p, 'Activa', 0);
        $this->variante($p, 'Inactiva', 30, ['is_active' => false]);

        $this->assertFalse($p->fresh()->isInStock());
        $this->assertFalse(Product::inStock()->whereKey($p->id)->exists());
    }

    public function test_sin_variantes_activas_manda_el_stock_propio(): void
    {
        $sinVariantes = $this->producto(['stock' => 3]);
        $agotado = $this->producto(['stock' => 0]);
        // Solo variantes inactivas: el cliente no elige color, se compra por el stock del producto.
        $soloInactivas = $this->producto(['stock' => 7]);
        $this->variante($soloInactivas, 'Vieja', 0, ['is_active' => false]);

        $this->assertTrue($sinVariantes->isInStock());
        $this->assertFalse($agotado->isInStock());
        $this->assertTrue($soloInactivas->fresh()->isInStock());

        $ids = Product::inStock()->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$sinVariantes->id, $soloInactivas->id], $ids);
        $this->assertSame([$agotado->id], Product::outOfStock()->pluck('id')->all());
    }

    // ---- «Sin stock» en el listado y la ficha ----

    public function test_el_listado_muestra_los_agotados_al_final_y_el_front_los_marca_con_las_variantes_en_cero(): void
    {
        $agotadoPorVariantes = $this->producto(['title' => 'Pistola', 'stock' => 9999, 'created_at' => now()]);
        $this->variante($agotadoPorVariantes, 'Rosa', 0);
        $this->variante($agotadoPorVariantes, 'Negra', 0);
        $agotado = $this->producto(['title' => 'AK-47', 'stock' => 0, 'created_at' => now()->subMinute()]);
        $disponible = $this->producto(['title' => 'Copa', 'stock' => 5, 'created_at' => now()->subDay()]);

        $this->get(route('products.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Products/Index')
            ->where('products.data', function ($data) use ($agotadoPorVariantes, $agotado, $disponible) {
                $data = collect($data);
                $this->assertSame($disponible->id, $data->first()['id'], 'Lo disponible va primero.');
                $this->assertEqualsCanonicalizing([$agotadoPorVariantes->id, $agotado->id], $data->slice(1)->pluck('id')->all());

                // Lo que el front necesita para escribir «Sin stock»: todas las variantes en 0.
                $pistola = $data->firstWhere('id', $agotadoPorVariantes->id);
                $this->assertSame([0, 0], collect($pistola['variants'])->pluck('stock')->all());

                return true;
            })
        );
    }

    public function test_la_ficha_de_un_producto_agotado_responde_y_trae_el_stock_en_cero(): void
    {
        $agotado = $this->producto(['stock' => 0]);
        $conVariantes = $this->producto(['stock' => 9999]);
        $this->variante($conVariantes, 'Rosa', 0);

        $this->get(route('products.show', $agotado))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('product.stock', 0));

        $this->get(route('products.show', $conVariantes))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('product.variants.0.stock', 0));
    }

    public function test_el_inicio_no_ofrece_destacados_agotados_por_variantes(): void
    {
        $agotado = $this->producto(['stock' => 9999, 'is_featured' => true]);
        $this->variante($agotado, 'Rosa', 0);
        $ok = $this->producto(['stock' => 5, 'is_featured' => true]);

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('featuredProducts', fn ($lista) => collect($lista)->pluck('id')->all() === [$ok->id])
        );
    }

    // ---- El carrito no pasa de lo disponible ----

    public function test_no_se_agrega_al_carrito_mas_que_el_stock_de_la_variante_y_el_mensaje_nombra_el_color(): void
    {
        $p = $this->producto(['title' => 'Pistola', 'stock' => 9999]);
        $rosa = $this->variante($p, 'Rosa', 2);

        $this->postJson(route('cart.add'), ['product_id' => $p->id, 'variant_id' => $rosa->id, 'quantity' => 3])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Solo quedan 2 unidades de Pistola (Rosa).');

        $this->postJson(route('cart.add'), ['product_id' => $p->id, 'variant_id' => $rosa->id, 'quantity' => 2])->assertOk();

        $this->postJson(route('cart.add'), ['product_id' => $p->id, 'variant_id' => $rosa->id, 'quantity' => 1])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Solo quedan 2 unidades de Pistola (Rosa) y ya tenés 2 en el carrito.');

        $this->assertSame(2, session('cart')[0]['quantity']);
    }

    public function test_una_variante_en_cero_no_se_puede_agregar(): void
    {
        $p = $this->producto(['title' => 'Pistola', 'stock' => 9999]);
        $negra = $this->variante($p, 'Negra', 0);

        $this->postJson(route('cart.add'), ['product_id' => $p->id, 'variant_id' => $negra->id])
            ->assertStatus(422)
            ->assertJsonPath('message', 'No queda stock de Pistola (Negra).');

        $this->assertEmpty(session('cart', []));
    }

    public function test_un_producto_en_cero_no_se_puede_agregar_y_uno_con_poco_no_pasa_del_tope(): void
    {
        $agotado = $this->producto(['title' => 'AK-47', 'stock' => 0]);
        $poco = $this->producto(['title' => 'Copa', 'stock' => 1]);

        $this->postJson(route('cart.add'), ['product_id' => $agotado->id])
            ->assertStatus(422)->assertJsonPath('message', 'No queda stock de AK-47.');

        $this->postJson(route('cart.add'), ['product_id' => $poco->id, 'quantity' => 2])
            ->assertStatus(422)->assertJsonPath('message', 'Solo quedan 1 unidad de Copa.');
    }

    public function test_subir_la_cantidad_en_el_carrito_tampoco_pasa_del_stock(): void
    {
        $p = $this->producto(['title' => 'Pistola', 'stock' => 9999]);
        $rosa = $this->variante($p, 'Rosa', 3);
        $this->postJson(route('cart.add'), ['product_id' => $p->id, 'variant_id' => $rosa->id])->assertOk();
        $lineKey = session('cart')[0]['line_key'];

        $this->patchJson(route('cart.update'), ['line_key' => $lineKey, 'quantity' => 4])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Solo quedan 3 unidades de Pistola (Rosa).');

        $this->patchJson(route('cart.update'), ['line_key' => $lineKey, 'quantity' => 3])->assertOk();
    }

    // ---- El checkout rechaza nombrando producto y color ----

    public function test_el_checkout_rechaza_un_color_sin_stock_nombrando_producto_y_color(): void
    {
        $p = $this->producto(['title' => 'Pistola lanza-chispas', 'stock' => 9999]);
        $rosa = $this->variante($p, 'Rosa', 0);

        $this->checkout([$this->lineaVariante($p, $rosa, 1)])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'No queda stock de Pistola lanza-chispas (Rosa). Ajustá tu carrito para continuar.')
            ->assertJsonPath('stock_insuficiente.0.variant_name', 'Rosa')
            ->assertJsonPath('stock_insuficiente.0.product_variant_id', $rosa->id);

        $this->assertSame(0, Order::count());
    }

    public function test_dos_colores_del_mismo_producto_sin_stock_se_informan_los_dos(): void
    {
        $p = $this->producto(['title' => 'Bengala', 'stock' => 9999]);
        $rojo = $this->variante($p, 'Rojo', 0);
        $azul = $this->variante($p, 'Azul', 1);

        $mensaje = $this->checkout([$this->lineaVariante($p, $rojo, 1), $this->lineaVariante($p, $azul, 4)])
            ->assertStatus(422)->json('message');

        $this->assertStringContainsString('No queda stock de Bengala (Rojo).', $mensaje);
        $this->assertStringContainsString('Solo quedan 1 unidad de Bengala (Azul) (pediste 4).', $mensaje);
        $this->assertSame(0, Order::count());
    }

    public function test_el_checkout_suma_el_mismo_color_repartido_en_dos_lineas(): void
    {
        // Mismo producto y color en dos líneas (distinto texto/add-on): 2 + 2 = 4 y solo hay 3.
        $p = $this->producto(['title' => 'Bengala', 'stock' => 9999]);
        $rojo = $this->variante($p, 'Rojo', 3);
        $a = $this->lineaVariante($p, $rojo, 2);
        $b = ['line_key' => 'otra'] + $this->lineaVariante($p, $rojo, 2);

        $this->checkout([$a, $b])
            ->assertStatus(422)
            ->assertJsonPath('stock_insuficiente.0.cantidad_solicitada', 4)
            ->assertJsonPath('stock_insuficiente.0.stock_disponible', 3);

        $this->assertSame(3, $rojo->fresh()->stock);
        $this->assertSame(0, Order::count());
    }

    public function test_el_faltante_bajo_lock_conserva_la_variante(): void
    {
        $p = $this->producto(['stock' => 9999]);
        $rojo = $this->variante($p, 'Rojo', 5);
        $order = Order::factory()->create();
        $order->items()->create([
            'product_id' => $p->id, 'product_variant_id' => $rojo->id, 'product_title' => $p->title,
            'cantidad' => 9, 'precio_unitario' => 1000, 'subtotal' => 9000,
        ]);

        try {
            app(StockService::class)->descontar($order);
            $this->fail('Se esperaba StockInsuficienteException.');
        } catch (StockInsuficienteException $e) {
            $this->assertSame($rojo->id, $e->variantId);
            $this->assertSame(5, $e->stockDisponible);
        }
    }

    // ---- Combos: la disponibilidad sale de los componentes ----

    private function combo(): array
    {
        $a = $this->producto(['title' => 'Bengala', 'stock' => 10]);
        $b = $this->producto(['title' => 'Volcán', 'stock' => 9999]);
        $rojo = $this->variante($b, 'Rojo', 8);
        $azul = $this->variante($b, 'Azul', 6);

        $combo = Combo::create(['title' => 'Combo Fiesta', 'price' => 5000, 'is_free_shipping' => false, 'is_active' => true]);
        $combo->items()->create(['product_id' => $a->id, 'quantity' => 1, 'sort_order' => 0]);
        $combo->items()->create(['product_id' => $b->id, 'quantity' => 2, 'sort_order' => 1]);

        return compact('combo', 'a', 'b', 'rojo', 'azul');
    }

    public function test_un_combo_sin_stock_por_un_componente_se_marca_en_la_ficha_y_en_el_listado(): void
    {
        $ctx = $this->combo();

        $this->get(route('combos.show', $ctx['combo']))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('combo.sin_stock', false));

        // Todos los colores del componente en 0: no se puede armar el combo.
        $ctx['rojo']->update(['stock' => 0]);
        $ctx['azul']->update(['stock' => 1]); // el combo pide 2 por unidad: 1 no alcanza

        $this->get(route('combos.show', $ctx['combo']))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('combo.sin_stock', true));

        $this->get(route('products.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('combos.0.sin_stock', true));

        // Un solo color con stock suficiente alcanza.
        $ctx['azul']->update(['stock' => 2]);
        $this->get(route('combos.show', $ctx['combo']))
            ->assertInertia(fn (Assert $page) => $page->where('combo.sin_stock', false));
    }

    public function test_un_combo_con_un_componente_agotado_no_se_agrega_y_el_mensaje_nombra_producto_y_color(): void
    {
        $ctx = $this->combo();
        $ctx['rojo']->update(['stock' => 1]);

        $this->postJson(route('cart.combo.add'), [
            'combo_id' => $ctx['combo']->id, 'quantity' => 1,
            'selections' => [['product_id' => $ctx['b']->id, 'variant_id' => $ctx['rojo']->id, 'custom_color_text' => null]],
        ])->assertStatus(422)->assertJsonPath('message', 'No hay stock suficiente de «Volcán (Rojo)» para este combo.');

        $this->assertEmpty(session('cart', []));
    }

    public function test_el_checkout_rechaza_un_combo_con_un_componente_sin_stock(): void
    {
        $ctx = $this->combo();
        $ctx['rojo']->update(['stock' => 3]); // 2 combos piden 4 de Volcán Rojo

        $this->checkout([[
            'line_key' => 'combo-1', 'combo_id' => $ctx['combo']->id, 'quantity' => 2,
            'component_selections' => [['product_id' => $ctx['b']->id, 'variant_id' => $ctx['rojo']->id, 'custom_color_text' => null]],
        ]])->assertStatus(422)
            ->assertJsonPath('message', 'Solo quedan 3 unidades de Volcán (Rojo) (pediste 4). Ajustá tu carrito para continuar.');

        $this->assertSame(0, Order::count());
        $this->assertSame(10, $ctx['a']->fresh()->stock);
    }

    // ---- Admin ----

    public function test_el_filtro_sin_stock_del_admin_incluye_los_productos_agotados_por_variantes(): void
    {
        $admin = User::factory()->admin()->create();
        $agotado = $this->producto(['stock' => 9999]);
        $this->variante($agotado, 'Rosa', 0);
        $ok = $this->producto(['stock' => 4]);

        $ids = fn (string $filtro) => collect(
            $this->actingAs($admin)->get(route('admin.products.index', ['stock' => $filtro]))->viewData('page')['props']['products']['data']
        )->pluck('id')->all();

        $this->assertSame([$agotado->id], $ids('out_of_stock'));
        $this->assertSame([$ok->id], $ids('in_stock'));
    }
}
