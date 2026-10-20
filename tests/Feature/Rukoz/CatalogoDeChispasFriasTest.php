<?php

namespace Tests\Feature\Rukoz;

use App\Models\Category;
use App\Models\Combo;
use App\Models\ComboItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Rukoz\CatalogoDeChispasFrias;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ContratoCatalogoCentral;
use Tests\TestCase;

/**
 * El proveedor genera el formato exacto de `POST /catalogo/sync` del Central. Cada caso se compara con el JSON
 * esperado y, además, todo el request armado se valida contra el contrato del Central (ContratoCatalogoCentral,
 * basado en el fixture oficial tests/fixtures/rukoz/request-catalogo-sync.json).
 */
class CatalogoDeChispasFriasTest extends TestCase
{
    use RefreshDatabase;

    private function proveedor(): CatalogoDeChispasFrias
    {
        return new CatalogoDeChispasFrias;
    }

    private function producto(string $titulo, float $precio, array $extra = []): Product
    {
        return Product::factory()->create($extra + [
            'title' => $titulo, 'price' => $precio, 'sku' => null, 'is_active' => true, 'stock' => 9999,
            'category_id' => Category::factory(),
        ]);
    }

    private function variante(Product $producto, string $nombre, array $extra = []): ProductVariant
    {
        return ProductVariant::create($extra + [
            'product_id' => $producto->id, 'name' => $nombre, 'color_hex' => '#ff0000',
            'price_addon' => 0, 'stock' => null, 'sku' => null, 'is_active' => true,
        ]);
    }

    private function combo(string $titulo, float $precio, array $atributos = []): Combo
    {
        return Combo::create($atributos + ['title' => $titulo, 'price' => $precio, 'is_active' => true]);
    }

    private function componente(Combo $combo, Product $producto, int $cantidad, int $orden = 0): ComboItem
    {
        return ComboItem::create([
            'combo_id' => $combo->id, 'product_id' => $producto->id, 'quantity' => $cantidad, 'sort_order' => $orden,
        ]);
    }

    /** Arma el request completo como lo haría el paquete (productos primero, combos después) y lo valida. */
    private function assertContratoValido(): array
    {
        $payload = [
            'completo' => true,
            'productos' => iterator_to_array($this->proveedor()->productos(), false),
            'combos' => iterator_to_array($this->proveedor()->combos(), false),
        ];

        $this->assertSame([], ContratoCatalogoCentral::errores($payload), 'El request no cumple el contrato del Central');

        return $payload;
    }

    public function test_producto_simple(): void
    {
        $p = $this->producto('Chispas Frias 2mts x 20segs cartucho', 5940, ['sku' => 'CF-2X20']);

        $this->assertSame([
            ['id' => (string) $p->id, 'nombre' => 'Chispas Frias 2mts x 20segs cartucho', 'sku' => 'CF-2X20', 'precio' => 5940.0, 'activo' => true],
        ], iterator_to_array($this->proveedor()->productos(), false));

        $this->assertContratoValido();
    }

    public function test_el_sku_vacio_se_manda_como_null_y_el_stock_no_viaja(): void
    {
        $this->producto('Sin sku', 100, ['sku' => '  ', 'stock' => 9999]);

        $item = iterator_to_array($this->proveedor()->productos(), false)[0];

        $this->assertNull($item['sku']);
        $this->assertArrayNotHasKey('stock', $item);
        $this->assertSame(['id', 'nombre', 'sku', 'precio', 'activo'], array_keys($item));
    }

    public function test_producto_con_variantes_manda_todas_con_el_precio_de_lista_mas_el_recargo(): void
    {
        $p = $this->producto('Bastón PULY®', 70400, ['sku' => 'BAS-1']);
        $negra = $this->variante($p, 'Negra', ['sort_order' => 0]);
        $roja = $this->variante($p, 'Roja', ['sort_order' => 1, 'price_addon' => 1500, 'sku' => 'BAS-R', 'stock' => 3]);
        $vieja = $this->variante($p, 'Vieja', ['sort_order' => 2, 'is_active' => false]);

        $this->assertSame([[
            'id' => (string) $p->id, 'nombre' => 'Bastón PULY®', 'sku' => 'BAS-1', 'precio' => 70400.0, 'activo' => true,
            'variantes' => [
                ['id' => (string) $negra->id, 'nombre' => 'Negra', 'sku' => null, 'precio' => 70400.0, 'activo' => true],
                ['id' => (string) $roja->id, 'nombre' => 'Roja', 'sku' => 'BAS-R', 'precio' => 71900.0, 'activo' => true],
                ['id' => (string) $vieja->id, 'nombre' => 'Vieja', 'sku' => null, 'precio' => 70400.0, 'activo' => false],
            ],
        ]], iterator_to_array($this->proveedor()->productos(), false));

        $this->assertContratoValido();
    }

    public function test_la_variante_a_eleccion_va_como_una_variante_mas(): void
    {
        $p = $this->producto('Pistola lanza-chispas', 104500);
        $dorada = $this->variante($p, 'Dorada', ['sort_order' => 0]);
        $otro = $this->variante($p, 'Otro color', [
            'sort_order' => 1, 'is_custom_color' => true, 'color_hex' => '#ffffff', 'price_addon' => 15000,
        ]);

        $variantes = iterator_to_array($this->proveedor()->productos(), false)[0]['variantes'];

        $this->assertSame([
            ['id' => (string) $dorada->id, 'nombre' => 'Dorada', 'sku' => null, 'precio' => 104500.0, 'activo' => true],
            ['id' => (string) $otro->id, 'nombre' => 'Otro color', 'sku' => null, 'precio' => 119500.0, 'activo' => true],
        ], $variantes);

        $this->assertContratoValido();
    }

    public function test_un_producto_inactivo_va_con_activo_false_y_sus_variantes_tambien(): void
    {
        $p = $this->producto('Descontinuado', 100, ['is_active' => false]);
        $v = $this->variante($p, 'Azul', ['is_active' => true]);

        $item = iterator_to_array($this->proveedor()->productos(), false)[0];

        $this->assertFalse($item['activo']);
        $this->assertFalse($item['variantes'][0]['activo']); // activa en sí, pero el producto está inactivo
        $this->assertSame((string) $v->id, $item['variantes'][0]['id']);

        $this->assertContratoValido();
    }

    public function test_solo_se_mandan_los_ids_pedidos(): void
    {
        $a = $this->producto('A', 10);
        $this->producto('B', 20);
        $c = $this->producto('C', 30);

        $ids = collect($this->proveedor()->productos([$a->id, $c->id]))->pluck('id')->all();

        $this->assertSame([(string) $a->id, (string) $c->id], $ids);
    }

    public function test_combo_fijo(): void
    {
        $detonador = $this->producto('Detonador', 99000);
        $cartucho = $this->producto('Cartucho', 5940);
        $combo = $this->combo('Kit boda', 150000);
        $this->componente($combo, $detonador, 1, 0);
        $this->componente($combo, $cartucho, 10, 1);

        $this->assertSame([[
            'id' => (string) $combo->id, 'nombre' => 'Kit boda', 'precio' => 150000.0, 'activo' => true,
            'tipo' => 'fijo',
            'items' => [
                ['tipo' => 'producto', 'id' => (string) $detonador->id, 'cantidad' => 1],
                ['tipo' => 'producto', 'id' => (string) $cartucho->id, 'cantidad' => 10],
            ],
        ]], iterator_to_array($this->proveedor()->combos(), false));

        $this->assertContratoValido();
    }

    public function test_un_producto_con_todas_las_variantes_inactivas_no_obliga_a_elegir_y_el_combo_sigue_fijo(): void
    {
        $p = $this->producto('Bengala', 4400);
        $this->variante($p, 'Roja', ['is_active' => false]);
        $combo = $this->combo('Pack', 8000);
        $this->componente($combo, $p, 2);

        $item = iterator_to_array($this->proveedor()->combos(), false)[0];

        $this->assertSame('fijo', $item['tipo']);
        $this->assertSame([['tipo' => 'producto', 'id' => (string) $p->id, 'cantidad' => 2]], $item['items']);
        $this->assertContratoValido();
    }

    public function test_combo_con_eleccion_de_color_un_grupo_por_componente(): void
    {
        $pistola = $this->producto('Pistola lanza-chispas', 104500);
        $dorada = $this->variante($pistola, 'Dorada', ['sort_order' => 0]);
        $otro = $this->variante($pistola, 'Otro color', ['sort_order' => 1, 'is_custom_color' => true, 'price_addon' => 15000]);
        $this->variante($pistola, 'Descartada', ['sort_order' => 2, 'is_active' => false]); // no es opción
        $cartucho = $this->producto('Cartucho', 5940);

        $combo = $this->combo('Pistola + cartuchos', 120000);
        $lineaPistola = $this->componente($combo, $pistola, 1, 0);
        $lineaCartucho = $this->componente($combo, $cartucho, 4, 1);

        $this->assertSame([[
            'id' => (string) $combo->id, 'nombre' => 'Pistola + cartuchos', 'precio' => 120000.0, 'activo' => true,
            'tipo' => 'seleccion',
            'grupos' => [
                ['nombre' => "Pistola lanza-chispas (#{$lineaPistola->id})", 'cantidad' => 1, 'opciones' => [
                    ['tipo' => 'variante', 'id' => (string) $dorada->id],
                    ['tipo' => 'variante', 'id' => (string) $otro->id],
                ]],
                ['nombre' => "Cartucho (#{$lineaCartucho->id})", 'cantidad' => 4, 'opciones' => [
                    ['tipo' => 'producto', 'id' => (string) $cartucho->id],
                ]],
            ],
        ]], iterator_to_array($this->proveedor()->combos(), false));

        $this->assertContratoValido();
    }

    public function test_los_nombres_de_grupo_son_unicos_aunque_dos_productos_se_llamen_igual(): void
    {
        $a = $this->producto('Igual', 10);
        $b = $this->producto('Igual', 20);
        $this->variante($a, 'Roja');
        $combo = $this->combo('C', 100);
        $this->componente($combo, $a, 1, 0);
        $this->componente($combo, $b, 1, 1);

        $nombres = collect(iterator_to_array($this->proveedor()->combos(), false)[0]['grupos'])->pluck('nombre');

        $this->assertCount(2, $nombres->unique());
        $this->assertContratoValido();
    }

    public function test_combo_inactivo_va_con_activo_false(): void
    {
        $p = $this->producto('P', 10);
        $combo = $this->combo('Viejo', 100, ['is_active' => false]);
        $this->componente($combo, $p, 1);

        $this->assertFalse(iterator_to_array($this->proveedor()->combos(), false)[0]['activo']);
        $this->assertContratoValido();
    }

    public function test_un_combo_sin_componentes_no_se_manda_porque_el_central_lo_rechazaria(): void
    {
        $this->combo('Vacío', 100);

        $this->assertSame([], iterator_to_array($this->proveedor()->combos(), false));
    }

    public function test_el_catalogo_del_ejemplo_completo_cumple_el_contrato(): void
    {
        // Producto simple + con variantes (con la «a elección») + combo fijo + combo con elección, todo junto.
        $simple = $this->producto('Cartucho', 5940);
        $pistola = $this->producto('Pistola', 104500);
        $this->variante($pistola, 'Dorada');
        $this->variante($pistola, 'Otro color', ['is_custom_color' => true, 'price_addon' => 15000]);
        $inactivo = $this->producto('Inactivo', 1, ['is_active' => false]);

        $fijo = $this->combo('Fijo', 1000);
        $this->componente($fijo, $simple, 3);
        $eleccion = $this->combo('Elección', 2000);
        $this->componente($eleccion, $pistola, 1, 0);
        $this->componente($eleccion, $simple, 2, 1);

        $payload = $this->assertContratoValido();

        $this->assertCount(3, $payload['productos']);
        $this->assertSame(['fijo', 'seleccion'], collect($payload['combos'])->pluck('tipo')->all());
        $this->assertFalse(collect($payload['productos'])->firstWhere('id', (string) $inactivo->id)['activo']);
    }

    public function test_el_validador_del_contrato_detecta_un_formato_malo(): void
    {
        // Red de seguridad del propio validador: si dejara pasar esto, los tests de arriba no probarían nada.
        $malo = ['completo' => true, 'productos' => [['id' => '1', 'nombre' => 'X', 'precio' => 10, 'activo' => true, 'stock' => 5]], 'combos' => []];

        $this->assertNotSame([], ContratoCatalogoCentral::errores($malo));
    }
}
