<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CHK-2c — «Compatible con tu pedido»: los productos que el admin marcó como compatibles con lo que hay en el carrito (relación
 * simétrica) y, solo si nada del carrito tiene compatibilidades, los marcados «sugerir siempre». El carrito vive en la sesión:
 * el endpoint no recibe ids.
 */
class SugerenciasCarritoTest extends TestCase
{
    use RefreshDatabase;

    private function producto(string $titulo, array $extra = []): Product
    {
        return Product::factory()->for(Category::factory()->create())->create(array_merge([
            'title' => $titulo,
            'price' => 1000,
            'stock' => 20,
            'is_active' => true,
            'sugerir_en_carrito' => false,
        ], $extra));
    }

    /** Carrito de sesión con una unidad de cada producto. */
    private function carritoCon(Product ...$productos): array
    {
        return array_map(fn (Product $p) => [
            'line_key' => "p{$p->id}",
            'product_id' => $p->id,
            'quantity' => 1,
            'variant_id' => null,
            'addon_selections' => [],
            'custom_color_text' => null,
        ], $productos);
    }

    /** @return array<int, string> */
    private function sugeridos(Product ...$enCarrito): array
    {
        return $this->withSession(['cart' => $this->carritoCon(...$enCarrito)])
            ->getJson(route('cart.suggestions'))
            ->assertOk()
            ->json('productos.*.title');
    }

    public function test_sugiere_lo_compatible_con_lo_que_hay_en_el_carrito(): void
    {
        $pistola = $this->producto('Pistola PULY');
        $chispa = $this->producto('Chispa Fria PULY');
        $this->producto('Pote de humo');

        $pistola->sincronizarCompatibles([$chispa->id]);

        $this->assertSame(['Chispa Fria PULY'], $this->sugeridos($pistola));
    }

    public function test_la_compatibilidad_es_simetrica(): void
    {
        $pistola = $this->producto('Pistola PULY');
        $baston = $this->producto('Baston PULY');
        $chispa = $this->producto('Chispa Fria PULY');

        // Se carga una sola vez, desde la chispa.
        $chispa->sincronizarCompatibles([$pistola->id, $baston->id]);

        $this->assertSame(['Chispa Fria PULY'], $this->sugeridos($pistola));
        $this->assertSame(['Chispa Fria PULY'], $this->sugeridos($baston));
        $this->assertSame(['Pistola PULY', 'Baston PULY'], $this->sugeridos($chispa));
    }

    public function test_lo_compatible_con_mas_productos_del_carrito_va_primero(): void
    {
        $pistola = $this->producto('Pistola PULY');
        $baston = $this->producto('Baston PULY');
        $chispa = $this->producto('Chispa PULY');
        $recarga = $this->producto('Recarga');

        // La recarga está primero en el orden del admin, pero la chispa sirve para los dos.
        $pistola->sincronizarCompatibles([$recarga->id, $chispa->id]);
        $baston->sincronizarCompatibles([$chispa->id]);

        $this->assertSame(['Chispa PULY', 'Recarga'], $this->sugeridos($pistola, $baston));
    }

    public function test_indica_con_que_productos_del_carrito_es_compatible(): void
    {
        $pistola = $this->producto('Pistola PULY');
        $chispa = $this->producto('Chispa PULY');
        $pistola->sincronizarCompatibles([$chispa->id]);

        $this->withSession(['cart' => $this->carritoCon($pistola)])
            ->getJson(route('cart.suggestions'))
            ->assertJsonPath('productos.0.compatible_con', ['Pistola PULY']);
    }

    public function test_no_sugiere_lo_que_ya_esta_en_el_carrito_ni_inactivos_ni_sin_stock(): void
    {
        $base = $this->producto('Base');
        $enCarrito = $this->producto('Ya en carrito');
        $inactivo = $this->producto('Inactivo', ['is_active' => false]);
        $agotado = $this->producto('Agotado', ['stock' => 0]);
        $disponible = $this->producto('Disponible');

        $base->sincronizarCompatibles([$enCarrito->id, $inactivo->id, $agotado->id, $disponible->id]);

        $this->assertSame(['Disponible'], $this->sugeridos($base, $enCarrito));
    }

    public function test_lo_que_viene_dentro_de_un_combo_cuenta_como_ya_en_el_carrito(): void
    {
        $base = $this->producto('Base');
        $enElCombo = $this->producto('Dentro del combo');
        $otro = $this->producto('Otro');
        $base->sincronizarCompatibles([$enElCombo->id, $otro->id]);

        $combo = Combo::create(['title' => 'Combo', 'price' => 3000, 'is_active' => true]);
        $combo->items()->create(['product_id' => $enElCombo->id, 'quantity' => 1, 'sort_order' => 0]);

        $carrito = array_merge($this->carritoCon($base), [[
            'line_key' => "combo:{$combo->id}",
            'combo_id' => $combo->id,
            'quantity' => 1,
            'component_selections' => [['product_id' => $enElCombo->id, 'variant_id' => null, 'custom_color_text' => null]],
        ]]);

        $this->withSession(['cart' => $carrito])
            ->getJson(route('cart.suggestions'))
            ->assertOk()
            ->assertJsonPath('productos.*.title', ['Otro']);
    }

    public function test_respeta_el_orden_elegido_por_el_admin(): void
    {
        $base = $this->producto('Base');
        $a = $this->producto('A');
        $b = $this->producto('B');

        $base->sincronizarCompatibles([$b->id, $a->id]);

        $this->assertSame(['B', 'A'], $this->sugeridos($base));
    }

    public function test_sugerir_siempre_solo_aplica_si_el_carrito_no_tiene_compatibilidades(): void
    {
        $this->producto('Chispa generica', ['sugerir_en_carrito' => true]);
        $sinReglas = $this->producto('Pote de humo');
        $pistola = $this->producto('Pistola PULY');
        $chispa = $this->producto('Chispa PULY');
        $pistola->sincronizarCompatibles([$chispa->id]);

        // Sin nada compatible cargado, se ofrecen las «siempre».
        $this->assertSame(['Chispa generica'], $this->sugeridos($sinReglas));
        // Con una pistola no se mezcla una chispa genérica...
        $this->assertSame(['Chispa PULY'], $this->sugeridos($pistola));
        // ...ni siquiera cuando su chispa compatible ya está en el carrito.
        $this->assertSame([], $this->sugeridos($pistola, $chispa));
    }

    public function test_carrito_vacio_no_devuelve_sugerencias(): void
    {
        $this->producto('Siempre', ['sugerir_en_carrito' => true]);

        $this->getJson(route('cart.suggestions'))->assertOk()->assertExactJson(['productos' => []]);
    }

    public function test_producto_con_colores_activos_marca_has_variants_y_uno_sin_colores_no(): void
    {
        $base = $this->producto('Base');
        $conColor = $this->producto('Con color');
        $simple = $this->producto('Simple');
        ProductVariant::factory()->create(['product_id' => $conColor->id, 'stock' => 5, 'is_active' => true]);
        ProductVariant::factory()->create(['product_id' => $simple->id, 'stock' => 5, 'is_active' => false]);
        $base->sincronizarCompatibles([$conColor->id, $simple->id]);

        $productos = collect($this->withSession(['cart' => $this->carritoCon($base)])->getJson(route('cart.suggestions'))->json('productos'))->keyBy('title');

        $this->assertTrue($productos['Con color']['has_variants']);
        $this->assertFalse($productos['Simple']['has_variants']);
    }

    public function test_un_producto_con_colores_pero_todos_agotados_no_se_sugiere(): void
    {
        $base = $this->producto('Base');
        $conColor = $this->producto('Con color', ['stock' => 9999]);
        ProductVariant::factory()->create(['product_id' => $conColor->id, 'stock' => 0, 'is_active' => true]);
        $base->sincronizarCompatibles([$conColor->id]);

        $this->assertSame([], $this->sugeridos($base));
    }

    public function test_el_precio_sugerido_incluye_la_oferta_vigente(): void
    {
        $base = $this->producto('Base');
        $conOferta = $this->producto('Con oferta', ['price' => 1000]);
        $conOferta->offers()->create([
            'offer_price' => 800,
            'is_active' => true,
            'tipo_descuento' => \App\Enums\TipoDescuento::Fijo,
            'valor_descuento' => 200,
            'alcance' => \App\Enums\AlcanceOferta::Todos,
        ]);
        $base->sincronizarCompatibles([$conOferta->id]);

        $this->withSession(['cart' => $this->carritoCon($base)])
            ->getJson(route('cart.suggestions'))
            ->assertJsonPath('productos.0.price', 800)
            ->assertJsonPath('productos.0.list_price', 1000);
    }

    public function test_agregar_una_sugerencia_suma_la_linea_con_sus_avisos(): void
    {
        $base = $this->producto('Base');
        $chispa = $this->producto('Chispa', ['stock' => 3]);
        $base->sincronizarCompatibles([$chispa->id]);

        $this->withSession(['cart' => $this->carritoCon($base)]);
        $this->postJson(route('cart.add'), ['product_id' => $chispa->id, 'quantity' => 1])->assertOk();

        $items = collect($this->get(route('cart.index'))->viewData('page')['props']['cartItems'])->keyBy(fn ($i) => $i['product']['id']);
        $this->assertSame(1, $items[$chispa->id]['quantity']);
        $this->assertSame(3, $items[$chispa->id]['stock_info']['tope']);
        // Ya en el carrito deja de sugerirse.
        $this->getJson(route('cart.suggestions'))->assertJsonPath('productos', []);
    }

    public function test_el_endpoint_tiene_limite_de_pedidos_por_minuto(): void
    {
        config(['tienda.limites.sugerencias_por_minuto' => 2]);

        foreach (range(1, 2) as $_) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.77'])->getJson(route('cart.suggestions'))->assertOk();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.77'])->getJson(route('cart.suggestions'))
            ->assertStatus(429)
            ->assertJsonPath('message', config('tienda.limites.mensaje'));

        // Otra IP no se ve afectada.
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.78'])->getJson(route('cart.suggestions'))->assertOk();
    }
}
