<?php

namespace Tests\Feature;

use App\Enums\MotivoMovimientoStock;
use App\Exceptions\StockInsuficienteException;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cobertura de StockService cuando los items de la orden traen product_variant_id:
 * el chequeo / descuento / reposición se aplican sobre el stock de la variante.
 */
class StockServiceVariantesTest extends TestCase
{
    use RefreshDatabase;

    private StockService $stockService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stockService = app(StockService::class);
    }

    private function itemConVariante(Order $order, Product $product, ProductVariant $variante, int $cantidad): void
    {
        $order->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variante->id,
            'product_title' => $product->title,
            'variant_name' => $variante->name,
            'cantidad' => $cantidad,
            'precio_unitario' => 100,
            'subtotal' => 100 * $cantidad,
        ]);
    }

    // --- validarDisponibilidad --------------------------------------------------

    public function test_validar_disponibilidad_chequea_el_stock_de_la_variante_no_el_del_producto(): void
    {
        $product = Product::factory()->create(['stock' => 100]);
        $variante = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'stock' => 2,
        ]);

        $faltantes = $this->stockService->validarDisponibilidad([
            ['product_id' => $product->id, 'product_variant_id' => $variante->id, 'cantidad' => 5],
        ]);

        $this->assertCount(1, $faltantes);
        $this->assertSame([
            'product_id' => $product->id,
            'product_variant_id' => $variante->id,
            'stock_disponible' => 2,
            'cantidad' => 5,
        ], $faltantes[0]);
    }

    public function test_validar_disponibilidad_ok_cuando_la_variante_tiene_stock(): void
    {
        $product = Product::factory()->create(['stock' => 0]);
        $variante = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'stock' => 10,
        ]);

        $faltantes = $this->stockService->validarDisponibilidad([
            ['product_id' => $product->id, 'product_variant_id' => $variante->id, 'cantidad' => 10],
        ]);

        $this->assertSame([], $faltantes);
    }

    public function test_validar_disponibilidad_variante_con_stock_ilimitado_siempre_ok(): void
    {
        $product = Product::factory()->create(['stock' => 0]);
        $variante = ProductVariant::factory()->stockIlimitado()->create([
            'product_id' => $product->id,
        ]);

        $faltantes = $this->stockService->validarDisponibilidad([
            ['product_id' => $product->id, 'product_variant_id' => $variante->id, 'cantidad' => 999],
        ]);

        $this->assertSame([], $faltantes);
    }

    // --- descontar -----------------------------------------------------------------

    public function test_descontar_resta_del_stock_de_la_variante_y_registra_el_movimiento_con_su_id(): void
    {
        $product = Product::factory()->create(['stock' => 50]);
        $variante = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'stock' => 8,
        ]);
        $order = Order::factory()->create();
        $this->itemConVariante($order, $product, $variante, 3);

        $this->stockService->descontar($order);

        $variante->refresh();
        $product->refresh();

        $this->assertSame(5, $variante->stock);
        $this->assertSame(50, $product->stock, 'El stock del producto no se toca cuando el item tiene variante.');

        $movimiento = StockMovement::where('order_id', $order->id)->first();
        $this->assertNotNull($movimiento);
        $this->assertSame($product->id, $movimiento->product_id);
        $this->assertSame($variante->id, $movimiento->product_variant_id);
        $this->assertSame(-3, $movimiento->cantidad);
        $this->assertSame(5, $movimiento->stock_resultante);
        $this->assertSame(MotivoMovimientoStock::OrdenCreada, $movimiento->motivo);
    }

    public function test_descontar_aborta_todo_si_la_variante_no_tiene_stock_suficiente(): void
    {
        $product = Product::factory()->create(['stock' => 100]);
        $varianteOk = ProductVariant::factory()->create(['product_id' => $product->id, 'stock' => 10]);
        $varianteSinStock = ProductVariant::factory()->create(['product_id' => $product->id, 'stock' => 1]);

        $order = Order::factory()->create();
        $this->itemConVariante($order, $product, $varianteOk, 2);
        $this->itemConVariante($order, $product, $varianteSinStock, 5);

        try {
            $this->stockService->descontar($order);
            $this->fail('Se esperaba StockInsuficienteException.');
        } catch (StockInsuficienteException $e) {
            $this->assertSame($product->id, $e->productId);
            $this->assertSame(5, $e->cantidadSolicitada);
            $this->assertSame(1, $e->stockDisponible);
        }

        $varianteOk->refresh();
        $varianteSinStock->refresh();

        $this->assertSame(10, $varianteOk->stock);
        $this->assertSame(1, $varianteSinStock->stock);
        $this->assertSame(0, StockMovement::where('order_id', $order->id)->count());
    }

    public function test_descontar_variante_con_stock_ilimitado_no_muta_ni_registra_movimiento(): void
    {
        $product = Product::factory()->create(['stock' => 50]);
        $variante = ProductVariant::factory()->stockIlimitado()->create(['product_id' => $product->id]);
        $order = Order::factory()->create();
        $this->itemConVariante($order, $product, $variante, 7);

        $this->stockService->descontar($order);

        $variante->refresh();
        $this->assertNull($variante->stock);
        $this->assertSame(0, StockMovement::where('order_id', $order->id)->count());
    }

    public function test_descontar_mezcla_item_con_variante_y_item_sin_variante(): void
    {
        $productoConVariante = Product::factory()->create(['stock' => 100]);
        $variante = ProductVariant::factory()->create(['product_id' => $productoConVariante->id, 'stock' => 6]);
        $productoSimple = Product::factory()->create(['stock' => 9]);

        $order = Order::factory()->create();
        $this->itemConVariante($order, $productoConVariante, $variante, 2);
        $order->items()->create([
            'product_id' => $productoSimple->id,
            'product_title' => $productoSimple->title,
            'cantidad' => 4,
            'precio_unitario' => 100,
            'subtotal' => 400,
        ]);

        $this->stockService->descontar($order);

        $variante->refresh();
        $productoConVariante->refresh();
        $productoSimple->refresh();

        $this->assertSame(4, $variante->stock);
        $this->assertSame(100, $productoConVariante->stock);
        $this->assertSame(5, $productoSimple->stock);
        $this->assertSame(2, StockMovement::where('order_id', $order->id)->count());
    }

    // --- reponer ---------------------------------------------------------------

    public function test_reponer_suma_al_stock_de_la_variante(): void
    {
        $product = Product::factory()->create(['stock' => 50]);
        $variante = ProductVariant::factory()->create(['product_id' => $product->id, 'stock' => 3]);
        $order = Order::factory()->create();
        $this->itemConVariante($order, $product, $variante, 4);

        $this->stockService->reponer($order);

        $variante->refresh();
        $this->assertSame(7, $variante->stock);

        $movimiento = StockMovement::where('order_id', $order->id)->first();
        $this->assertSame($variante->id, $movimiento->product_variant_id);
        $this->assertSame(4, $movimiento->cantidad);
        $this->assertSame(MotivoMovimientoStock::OrdenCancelada, $movimiento->motivo);
    }

    public function test_reponer_llamado_dos_veces_no_duplica_la_reposicion_de_la_variante(): void
    {
        $product = Product::factory()->create(['stock' => 50]);
        $variante = ProductVariant::factory()->create(['product_id' => $product->id, 'stock' => 3]);
        $order = Order::factory()->create();
        $this->itemConVariante($order, $product, $variante, 4);

        $this->stockService->reponer($order);
        $this->stockService->reponer($order);

        $variante->refresh();
        $this->assertSame(7, $variante->stock);
        $this->assertSame(
            1,
            StockMovement::where('order_id', $order->id)
                ->where('motivo', MotivoMovimientoStock::OrdenCancelada)
                ->count()
        );
    }
}
