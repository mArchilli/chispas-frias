<?php

namespace Tests\Feature;

use App\Enums\MotivoMovimientoStock;
use App\Exceptions\StockInsuficienteException;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockService $stockService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stockService = app(StockService::class);
    }

    // --- validarDisponibilidad ---------------------------------------------------

    public function test_validar_disponibilidad_devuelve_vacio_cuando_hay_stock_para_todo(): void
    {
        $productoA = Product::factory()->create(['stock' => 10]);
        $productoB = Product::factory()->create(['stock' => 5]);

        $faltantes = $this->stockService->validarDisponibilidad([
            ['product_id' => $productoA->id, 'cantidad' => 3],
            ['product_id' => $productoB->id, 'cantidad' => 5],
        ]);

        $this->assertSame([], $faltantes);
    }

    public function test_validar_disponibilidad_reporta_items_sin_stock_suficiente(): void
    {
        $productoOk = Product::factory()->create(['stock' => 10]);
        $productoFaltante = Product::factory()->create(['stock' => 3]);

        $faltantes = $this->stockService->validarDisponibilidad([
            ['product_id' => $productoOk->id, 'cantidad' => 2],
            ['product_id' => $productoFaltante->id, 'cantidad' => 5],
        ]);

        $this->assertCount(1, $faltantes);
        $this->assertSame([
            'product_id' => $productoFaltante->id,
            'stock_disponible' => 3,
            'cantidad' => 5,
        ], $faltantes[0]);
    }

    // --- descontar -----------------------------------------------------------------

    public function test_descontar_resta_stock_y_crea_movimiento(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $order = Order::factory()->create();
        $order->items()->create([
            'product_id' => $product->id,
            'product_title' => $product->title,
            'cantidad' => 3,
            'precio_unitario' => 100,
            'subtotal' => 300,
        ]);

        $this->stockService->descontar($order);

        $product->refresh();
        $this->assertSame(7, $product->stock);

        $movimiento = StockMovement::where('product_id', $product->id)
            ->where('order_id', $order->id)
            ->first();

        $this->assertNotNull($movimiento);
        $this->assertSame(-3, $movimiento->cantidad);
        $this->assertSame(MotivoMovimientoStock::OrdenCreada, $movimiento->motivo);
        $this->assertSame(7, $movimiento->stock_resultante);
    }

    public function test_descontar_ignora_items_con_product_id_nulo(): void
    {
        $order = Order::factory()->create();
        $order->items()->create([
            'product_id' => null,
            'product_title' => 'Producto borrado',
            'cantidad' => 2,
            'precio_unitario' => 50,
            'subtotal' => 100,
        ]);

        $this->stockService->descontar($order);

        $this->assertSame(0, StockMovement::where('order_id', $order->id)->count());
    }

    public function test_descontar_aborta_todo_si_un_item_no_tiene_stock_suficiente(): void
    {
        $productoConStock = Product::factory()->create(['stock' => 10]);
        $productoSinStock = Product::factory()->create(['stock' => 1]);

        $order = Order::factory()->create();
        $order->items()->create([
            'product_id' => $productoConStock->id,
            'product_title' => $productoConStock->title,
            'cantidad' => 2,
            'precio_unitario' => 100,
            'subtotal' => 200,
        ]);
        $order->items()->create([
            'product_id' => $productoSinStock->id,
            'product_title' => $productoSinStock->title,
            'cantidad' => 5,
            'precio_unitario' => 50,
            'subtotal' => 250,
        ]);

        try {
            $this->stockService->descontar($order);
            $this->fail('Se esperaba StockInsuficienteException.');
        } catch (StockInsuficienteException $e) {
            $this->assertSame($productoSinStock->id, $e->productId);
            $this->assertSame(5, $e->cantidadSolicitada);
            $this->assertSame(1, $e->stockDisponible);
        }

        $productoConStock->refresh();
        $productoSinStock->refresh();

        $this->assertSame(10, $productoConStock->stock);
        $this->assertSame(1, $productoSinStock->stock);
        $this->assertSame(0, StockMovement::where('order_id', $order->id)->count());
    }

    // --- reponer ---------------------------------------------------------------

    public function test_reponer_suma_stock_y_crea_movimiento(): void
    {
        $product = Product::factory()->create(['stock' => 5]);
        $order = Order::factory()->create();
        $order->items()->create([
            'product_id' => $product->id,
            'product_title' => $product->title,
            'cantidad' => 4,
            'precio_unitario' => 100,
            'subtotal' => 400,
        ]);

        $this->stockService->reponer($order);

        $product->refresh();
        $this->assertSame(9, $product->stock);

        $movimiento = StockMovement::where('product_id', $product->id)
            ->where('order_id', $order->id)
            ->first();

        $this->assertNotNull($movimiento);
        $this->assertSame(4, $movimiento->cantidad);
        $this->assertSame(MotivoMovimientoStock::OrdenCancelada, $movimiento->motivo);
        $this->assertSame(9, $movimiento->stock_resultante);
    }

    public function test_reponer_ignora_items_con_product_id_nulo(): void
    {
        $order = Order::factory()->create();
        $order->items()->create([
            'product_id' => null,
            'product_title' => 'Producto borrado',
            'cantidad' => 2,
            'precio_unitario' => 50,
            'subtotal' => 100,
        ]);

        $this->stockService->reponer($order);

        $this->assertSame(0, StockMovement::where('order_id', $order->id)->count());
    }

    public function test_reponer_llamado_dos_veces_no_duplica_la_reposicion(): void
    {
        $product = Product::factory()->create(['stock' => 5]);
        $order = Order::factory()->create();
        $order->items()->create([
            'product_id' => $product->id,
            'product_title' => $product->title,
            'cantidad' => 4,
            'precio_unitario' => 100,
            'subtotal' => 400,
        ]);

        $this->stockService->reponer($order);
        $this->stockService->reponer($order);

        $product->refresh();
        $this->assertSame(9, $product->stock);
        $this->assertSame(
            1,
            StockMovement::where('order_id', $order->id)
                ->where('motivo', MotivoMovimientoStock::OrdenCancelada)
                ->count()
        );
    }
}
