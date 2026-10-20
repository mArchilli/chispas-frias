<?php

namespace Tests\Feature\Rukoz;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ContratoPedidoCentral;
use Tests\TestCase;

/**
 * El validador de contrato (tests/Support/ContratoPedidoCentral) tiene que rechazar de verdad lo que el Central 0.1.2
 * rechazaría: si aceptara todo, los tests del checkout que dependen de él no probarían nada.
 */
class ContratoPedidoCentralTest extends TestCase
{
    use RefreshDatabase;

    private function ejemplo(): array
    {
        return json_decode((string) file_get_contents(base_path('tests/fixtures/rukoz/request-pedido-crear.json')), true);
    }

    public function test_el_ejemplo_oficial_del_central_pasa_forma_y_campos(): void
    {
        $this->assertSame([], ContratoPedidoCentral::errores($this->ejemplo(), contraCatalogo: false));
    }

    public function test_los_fixtures_son_los_del_paquete_instalado(): void
    {
        $dir = base_path('vendor/rukoz/core-client/tests/fixtures/ejemplos');

        foreach (['request-pedido-crear', 'request-pedido-cancelar', 'response-pedido-reservado', 'response-pedido-cancelado', 'error-sin-stock', 'error-item-no-vinculado', 'error-pedido-ya-existe'] as $nombre) {
            $this->assertFileEquals("{$dir}/{$nombre}.json", base_path("tests/fixtures/rukoz/{$nombre}.json"), "El fixture {$nombre} se desactualizó respecto del paquete (rukoz/core-client)");
        }
    }

    public function test_rechaza_claves_que_el_contrato_no_conoce_y_los_topes(): void
    {
        $pedido = $this->ejemplo();
        $pedido['envio'] = 1500;                       // el envío no se manda en la reserva
        $pedido['items'][0]['descuento'] = 10;         // el descuento no se manda por línea
        $pedido['items'][0]['nota'] = str_repeat('x', 501);
        $pedido['cliente']['dni'] = str_repeat('1', 31);

        $errores = implode(' | ', ContratoPedidoCentral::errores($pedido, contraCatalogo: false));

        $this->assertStringContainsString('«envio»', $errores);
        $this->assertStringContainsString('«descuento»', $errores);
        $this->assertStringContainsString('items.0.nota', $errores);
        $this->assertStringContainsString('cliente.dni', $errores);
    }

    public function test_rechaza_una_nota_vacia_y_selecciones_en_algo_que_no_es_un_combo(): void
    {
        $pedido = $this->ejemplo();
        $pedido['items'][0]['nota'] = '  ';
        $pedido['items'][0]['selecciones'] = [['grupo' => 'x', 'opciones' => [['tipo' => 'variante', 'id' => '1']]]];

        $errores = implode(' | ', ContratoPedidoCentral::errores($pedido, contraCatalogo: false));

        $this->assertStringContainsString('una nota vacía no se manda', $errores);
        $this->assertStringContainsString('solo un combo lleva selecciones', $errores);
    }

    public function test_un_producto_con_variantes_no_puede_viajar_como_producto(): void
    {
        $producto = Product::factory()->for(Category::factory())->create(['is_active' => true]);
        $variante = ProductVariant::factory()->create(['product_id' => $producto->id, 'is_active' => true]);
        $base = ['external_ref' => 'CHF-X', 'cliente' => ['nombre' => 'N'], 'total' => 10, 'moneda' => 'ARS'];

        $malo = $base + ['items' => [['tipo' => 'producto', 'id' => (string) $producto->id, 'cantidad' => 1, 'precio_unitario' => 10]]];
        $bueno = $base + ['items' => [['tipo' => 'variante', 'id' => (string) $variante->id, 'cantidad' => 1, 'precio_unitario' => 10]]];

        $this->assertStringContainsString('tiene variantes', implode(' | ', ContratoPedidoCentral::errores($malo)));
        $this->assertSame([], ContratoPedidoCentral::errores($bueno));
    }

    public function test_las_selecciones_de_un_combo_se_validan_contra_los_grupos_del_catalogo(): void
    {
        $pistola = Product::factory()->for(Category::factory())->create(['title' => 'Pistola', 'is_active' => true]);
        $roja = ProductVariant::factory()->create(['product_id' => $pistola->id, 'name' => 'Roja', 'is_active' => true]);
        $combo = Combo::create(['title' => 'Combo', 'price' => 100, 'is_free_shipping' => false, 'is_active' => true]);
        $linea = $combo->items()->create(['product_id' => $pistola->id, 'quantity' => 2, 'sort_order' => 0]);

        $pedido = fn (array $selecciones) => ['external_ref' => 'CHF-X', 'cliente' => ['nombre' => 'N'], 'total' => 100, 'moneda' => 'ARS', 'items' => [[
            'tipo' => 'combo', 'id' => (string) $combo->id, 'cantidad' => 1, 'precio_unitario' => 100, 'selecciones' => $selecciones,
        ]]];
        $grupo = "Pistola (#{$linea->id})";

        $this->assertSame([], ContratoPedidoCentral::errores($pedido([['grupo' => $grupo, 'opciones' => [['tipo' => 'variante', 'id' => (string) $roja->id, 'cantidad' => 2]]]])));
        $this->assertStringContainsString('no existe en el catálogo del combo', implode(' | ', ContratoPedidoCentral::errores($pedido([['grupo' => 'Otro', 'opciones' => [['tipo' => 'variante', 'id' => (string) $roja->id, 'cantidad' => 2]]]]))));
        $this->assertStringContainsString('las cantidades suman 1 y el grupo pide 2', implode(' | ', ContratoPedidoCentral::errores($pedido([['grupo' => $grupo, 'opciones' => [['tipo' => 'variante', 'id' => (string) $roja->id, 'cantidad' => 1]]]]))));
        $this->assertStringContainsString('el combo tiene 1 grupos y se mandaron 0', implode(' | ', ContratoPedidoCentral::errores($pedido([]))));
    }
}
