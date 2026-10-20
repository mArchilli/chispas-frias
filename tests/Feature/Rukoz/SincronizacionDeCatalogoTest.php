<?php

namespace Tests\Feature\Rukoz;

use App\Models\Category;
use App\Models\Combo;
use App\Models\ComboItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Rukoz\CatalogoDeChispasFrias;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Rukoz\CoreClient\Contratos\ProveedorCatalogo;
use Tests\Support\RukozFalso;
use Tests\TestCase;

/**
 * Guardar en el admin manda el sync parcial al Central solo con INTEGRACION_CATALOGO encendido,
 * y nunca rompe el guardado.
 */
class SincronizacionDeCatalogoTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function datosDeProducto(array $extra = []): array
    {
        return $extra + [
            'title' => 'Chispa nueva', 'description' => 'Una chispa.', 'price' => 1000,
            'category_id' => Category::factory()->create()->id, 'stock' => 10, 'is_active' => '1',
        ];
    }

    private function datosDeCombo(Product $producto, array $extra = []): array
    {
        return $extra + [
            'title' => 'Combo', 'price' => 5000, 'is_active' => '1',
            'items' => [['product_id' => $producto->id, 'quantity' => 1]],
        ];
    }

    public function test_por_defecto_la_integracion_esta_apagada_en_la_suite(): void
    {
        $this->assertFalse((bool) config('integracion.catalogo'));
        $this->assertFalse((bool) config('integracion.stock'));
        $this->assertFalse((bool) config('integracion.checkout'));
        $this->assertFalse((bool) config('integracion.confirmacion'));
        $this->assertFalse((bool) config('rukoz.habilitada'));
        $this->assertFalse(app()->bound(ProveedorCatalogo::class));
    }

    public function test_crear_un_producto_con_el_interruptor_encendido_sincroniza_ese_producto(): void
    {
        config(['integracion.catalogo' => true]);
        $falso = RukozFalso::instalar();

        $this->actingAs($this->admin())
            ->post(route('admin.products.store'), $this->datosDeProducto())
            ->assertRedirect(route('admin.products.index'));

        $producto = Product::where('title', 'Chispa nueva')->firstOrFail();
        $this->assertSame([[$producto->id]], $falso->idsDe('sincronizarProductos'));
        $this->assertSame([], $falso->idsDe('sincronizarCombos'));
    }

    public function test_guardar_un_producto_con_el_interruptor_apagado_no_llama_al_sync(): void
    {
        config(['integracion.catalogo' => false]);
        $falso = RukozFalso::instalar();

        $producto = Product::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.products.store'), $this->datosDeProducto())
            ->assertRedirect(route('admin.products.index'));

        $this->actingAs($admin)
            ->put(route('admin.products.update', $producto), $this->datosDeProducto(['title' => 'Otro']))
            ->assertRedirect(route('admin.products.index'));

        $this->actingAs($admin)->patch(route('admin.products.toggle-status', $producto))->assertRedirect();
        $this->actingAs($admin)->delete(route('admin.products.destroy', $producto))->assertRedirect();

        $this->assertSame([], $falso->llamadas);
        $this->assertDatabaseHas('products', ['title' => 'Chispa nueva']);
        $this->assertDatabaseMissing('products', ['id' => $producto->id]);
    }

    public function test_editar_un_producto_sincroniza_el_producto_y_despues_los_combos_que_lo_contienen(): void
    {
        config(['integracion.catalogo' => true]);

        $producto = Product::factory()->create();
        $otro = Product::factory()->create();
        $combo = Combo::create(['title' => 'C', 'price' => 1, 'is_active' => true]);
        ComboItem::create(['combo_id' => $combo->id, 'product_id' => $producto->id, 'quantity' => 1, 'sort_order' => 0]);
        $sinRelacion = Combo::create(['title' => 'D', 'price' => 1, 'is_active' => true]);
        ComboItem::create(['combo_id' => $sinRelacion->id, 'product_id' => $otro->id, 'quantity' => 1, 'sort_order' => 0]);

        $falso = RukozFalso::instalar();

        $this->actingAs($this->admin())
            ->put(route('admin.products.update', $producto), $this->datosDeProducto())
            ->assertRedirect(route('admin.products.index'));

        // Primero el producto, después el combo que lo contiene (el combo referencia productos ya enviados).
        $this->assertSame([
            ['sincronizarProductos', [$producto->id]],
            ['sincronizarCombos', [$combo->id]],
        ], $falso->llamadas);
    }

    public function test_guardar_un_producto_sin_cambios_manda_el_sync_parcial(): void
    {
        config(['integracion.catalogo' => true]);
        $producto = Product::factory()->create(['stock' => 9999]);
        $falso = RukozFalso::instalar();

        $this->actingAs($this->admin())
            ->put(route('admin.products.update', $producto), [
                'title' => $producto->title, 'description' => $producto->description, 'price' => $producto->price,
                'category_id' => $producto->category_id, 'stock' => 9999, 'is_active' => $producto->is_active ? '1' : '0',
            ])
            ->assertRedirect(route('admin.products.index'));

        $this->assertSame([[$producto->id]], $falso->idsDe('sincronizarProductos'));
    }

    public function test_activar_o_desactivar_un_producto_lo_sincroniza(): void
    {
        config(['integracion.catalogo' => true]);
        $producto = Product::factory()->create(['is_active' => true]);
        $falso = RukozFalso::instalar();

        $this->actingAs($this->admin())->patch(route('admin.products.toggle-status', $producto))->assertRedirect();

        $this->assertSame([[$producto->id]], $falso->idsDe('sincronizarProductos'));
        $this->assertFalse($producto->fresh()->is_active);
    }

    public function test_marcar_destacado_no_toca_el_catalogo(): void
    {
        config(['integracion.catalogo' => true]);
        $producto = Product::factory()->create();
        $falso = RukozFalso::instalar();

        $this->actingAs($this->admin())->patch(route('admin.products.toggle-featured', $producto))->assertRedirect();

        $this->assertSame([], $falso->llamadas);
    }

    public function test_quitar_una_variante_la_avisa_como_inactiva(): void
    {
        config(['integracion.catalogo' => true]);
        $producto = Product::factory()->create(['price' => 100, 'stock' => 9999]);
        $queda = ProductVariant::factory()->create(['product_id' => $producto->id, 'name' => 'Queda', 'price_addon' => 0, 'sku' => null]);
        $sale = ProductVariant::factory()->create(['product_id' => $producto->id, 'name' => 'Sale', 'price_addon' => 50, 'sku' => null]);
        $falso = RukozFalso::instalar();

        $this->actingAs($this->admin())
            ->put(route('admin.products.update', $producto), [
                'title' => $producto->title, 'description' => 'x', 'price' => 100, 'category_id' => $producto->category_id,
                'stock' => 9999, 'is_active' => '1',
                'variants' => [['id' => $queda->id, 'name' => 'Queda', 'color_hex' => '#ff0000', 'price_addon' => 0, 'stock' => 5, 'is_active' => '1', 'is_custom_color' => '0']],
            ])
            ->assertRedirect(route('admin.products.index'));

        $this->assertDatabaseMissing('product_variants', ['id' => $sale->id]);
        $this->assertSame([[$producto->id]], $falso->idsDe('sincronizarProductos'));

        $payload = $falso->idsDe('syncCatalogo')[0][0];
        $this->assertFalse($payload['completo']);
        $variantes = collect($payload['productos'][0]['variantes'])->keyBy('id');
        $this->assertTrue($variantes[(string) $queda->id]['activo']);
        $this->assertFalse($variantes[(string) $sale->id]['activo']);
        $this->assertSame(150.0, $variantes[(string) $sale->id]['precio']);
    }

    public function test_borrar_un_producto_lo_avisa_inactivo_y_reenvia_los_combos_que_lo_contenian(): void
    {
        config(['integracion.catalogo' => true]);
        $producto = Product::factory()->create(['is_active' => true]);
        $variante = ProductVariant::factory()->create(['product_id' => $producto->id, 'is_active' => true]);
        $otro = Product::factory()->create();
        $combo = Combo::create(['title' => 'C', 'price' => 1, 'is_active' => true]);
        ComboItem::create(['combo_id' => $combo->id, 'product_id' => $producto->id, 'quantity' => 1, 'sort_order' => 0]);
        ComboItem::create(['combo_id' => $combo->id, 'product_id' => $otro->id, 'quantity' => 1, 'sort_order' => 1]);
        $falso = RukozFalso::instalar();

        $this->actingAs($this->admin())->delete(route('admin.products.destroy', $producto))->assertRedirect();

        $this->assertDatabaseMissing('products', ['id' => $producto->id]);

        [$metodo, $args] = $falso->llamadas[0];
        $this->assertSame('syncCatalogo', $metodo);
        $item = $args[0]['productos'][0];
        $this->assertSame((string) $producto->id, $item['id']);
        $this->assertFalse($item['activo']);
        $this->assertFalse($item['variantes'][0]['activo']);
        $this->assertSame((string) $variante->id, $item['variantes'][0]['id']);

        $this->assertSame(['sincronizarCombos', [$combo->id]], $falso->llamadas[1]);
    }

    public function test_crear_y_editar_un_combo_sincroniza_ese_combo(): void
    {
        config(['integracion.catalogo' => true]);
        $falso = RukozFalso::instalar();
        $producto = Product::factory()->create();

        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.combos.store'), $this->datosDeCombo($producto))
            ->assertRedirect(route('admin.combos.index'));
        $combo = Combo::firstOrFail();
        $this->actingAs($admin)->put(route('admin.combos.update', $combo), $this->datosDeCombo($producto))
            ->assertRedirect(route('admin.combos.index'));
        $this->actingAs($admin)->patch(route('admin.combos.toggle-status', $combo))->assertRedirect();

        $this->assertSame(
            [['sincronizarCombos', [$combo->id]], ['sincronizarCombos', [$combo->id]], ['sincronizarCombos', [$combo->id]]],
            $falso->llamadas
        );
    }

    public function test_borrar_un_combo_lo_avisa_inactivo(): void
    {
        config(['integracion.catalogo' => true]);
        $producto = Product::factory()->create();
        $combo = Combo::create(['title' => 'C', 'price' => 1, 'is_active' => true]);
        ComboItem::create(['combo_id' => $combo->id, 'product_id' => $producto->id, 'quantity' => 2, 'sort_order' => 0]);
        $falso = RukozFalso::instalar();

        $this->actingAs($this->admin())->delete(route('admin.combos.destroy', $combo))->assertRedirect();

        $this->assertDatabaseMissing('combos', ['id' => $combo->id]);
        $item = $falso->idsDe('syncCatalogo')[0][0]['combos'][0];
        $this->assertSame((string) $combo->id, $item['id']);
        $this->assertFalse($item['activo']);
        $this->assertSame('fijo', $item['tipo']);
    }

    public function test_guardar_un_combo_con_el_interruptor_apagado_no_llama_al_sync(): void
    {
        config(['integracion.catalogo' => false]);
        $falso = RukozFalso::instalar();
        $producto = Product::factory()->create();

        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.combos.store'), $this->datosDeCombo($producto))
            ->assertRedirect(route('admin.combos.index'));
        $combo = Combo::firstOrFail();
        $this->actingAs($admin)->patch(route('admin.combos.toggle-status', $combo))->assertRedirect();
        $this->actingAs($admin)->delete(route('admin.combos.destroy', $combo))->assertRedirect();

        $this->assertSame([], $falso->llamadas);
        $this->assertDatabaseMissing('combos', ['id' => $combo->id]);
    }

    public function test_si_el_sync_falla_el_guardado_funciona_igual(): void
    {
        config(['integracion.catalogo' => true]);
        $falso = RukozFalso::instalar();
        $falso->alLlamar = fn () => throw new \RuntimeException('boom');

        $this->actingAs($this->admin())
            ->post(route('admin.products.store'), $this->datosDeProducto())
            ->assertRedirect(route('admin.products.index'))
            ->assertSessionHasNoErrors();

        $this->assertCount(1, $falso->llamadas);
        $this->assertDatabaseHas('products', ['title' => 'Chispa nueva']);
    }

    public function test_con_el_central_caido_el_guardado_funciona_y_el_id_queda_diferido(): void
    {
        // Paquete real (sin mocks), integración encendida, y el Central sin responder.
        config([
            'integracion.catalogo' => true,
            'rukoz.habilitada' => true,
            'rukoz.url' => 'http://central.test',
            'rukoz.api_key' => 'rk_prueba',
            'rukoz.secret' => 'secreto-de-prueba',
            'rukoz.reintentos' => 0,
        ]);
        app()->bind(ProveedorCatalogo::class, CatalogoDeChispasFrias::class);
        Http::fake(fn () => throw new ConnectionException('Central caído'));

        $this->actingAs($this->admin())
            ->post(route('admin.products.store'), $this->datosDeProducto(['title' => 'Con el central caído']))
            ->assertRedirect(route('admin.products.index'))
            ->assertSessionHasNoErrors();

        $producto = Product::where('title', 'Con el central caído')->firstOrFail();
        $this->assertDatabaseHas('rukoz_estado', ['clave' => 'catalogo_parcial_pendiente']);
        $this->assertStringContainsString(
            (string) $producto->id,
            (string) DB::table('rukoz_estado')->where('clave', 'catalogo_parcial_pendiente')->value('valor')
        );
    }

    public function test_con_el_central_caido_borrar_y_quitar_variantes_tampoco_rompen_nada(): void
    {
        config([
            'integracion.catalogo' => true,
            'rukoz.habilitada' => true,
            'rukoz.url' => 'http://central.test',
            'rukoz.api_key' => 'rk_prueba',
            'rukoz.secret' => 'secreto-de-prueba',
            'rukoz.reintentos' => 0,
        ]);
        app()->bind(ProveedorCatalogo::class, CatalogoDeChispasFrias::class);
        Http::fake(fn () => throw new ConnectionException('Central caído'));

        $producto = Product::factory()->create();
        $combo = Combo::create(['title' => 'C', 'price' => 1, 'is_active' => true]);
        ComboItem::create(['combo_id' => $combo->id, 'product_id' => $producto->id, 'quantity' => 1, 'sort_order' => 0]);
        $admin = $this->admin();

        $this->actingAs($admin)->delete(route('admin.products.destroy', $producto))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('products', ['id' => $producto->id]);

        $otro = Product::factory()->create();
        $combo2 = Combo::create(['title' => 'D', 'price' => 1, 'is_active' => true]);
        ComboItem::create(['combo_id' => $combo2->id, 'product_id' => $otro->id, 'quantity' => 1, 'sort_order' => 0]);
        $this->actingAs($admin)->delete(route('admin.combos.destroy', $combo2))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('combos', ['id' => $combo2->id]);
    }
}
