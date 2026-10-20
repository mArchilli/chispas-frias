<?php

namespace Tests\Feature;

use App\Exceptions\DiscountCodeInvalidoException;
use App\Models\Category;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\DiscountCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Consumir y liberar el uso de un código de descuento son IDEMPOTENTES: la primera llamada deja
 * una marca de tiempo en el pedido (discount_usage_consumido_at / discount_usage_liberado_at) y
 * cualquier repetición —un reintento, un evento duplicado— no vuelve a tocar el contador.
 */
class DiscountCodeIdempotenciaTest extends TestCase
{
    use RefreshDatabase;

    private DiscountCodeService $servicio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->servicio = app(DiscountCodeService::class);
    }

    private function codigo(array $attrs = []): DiscountCode
    {
        return DiscountCode::create(array_merge([
            'code' => 'VERANO10', 'percentage' => 10, 'usage_limit' => 5, 'usage_count' => 0, 'is_active' => true,
        ], $attrs));
    }

    private function pedido(?DiscountCode $codigo, array $attrs = []): Order
    {
        return Order::factory()->create(array_merge([
            'discount_code_id' => $codigo?->id,
            'discount_code' => $codigo?->code,
        ], $attrs));
    }

    // --- consumir -------------------------------------------------------------------

    public function test_consumir_dos_veces_suma_un_solo_uso_y_deja_la_marca(): void
    {
        $codigo = $this->codigo();
        $pedido = $this->pedido($codigo);

        $this->servicio->consumirUso($pedido);
        $this->servicio->consumirUso($pedido);
        $this->servicio->consumirUso(Order::find($pedido->id)); // otra instancia: un reintento

        $this->assertSame(1, $codigo->fresh()->usage_count);
        $this->assertNotNull($pedido->fresh()->discount_usage_consumido_at);
        $this->assertNull($pedido->fresh()->discount_usage_liberado_at);
        // La instancia que llamó refleja la marca sin quedar "sucia".
        $this->assertNotNull($pedido->discount_usage_consumido_at);
        $this->assertFalse($pedido->isDirty());
    }

    public function test_dos_instancias_viejas_del_mismo_pedido_consumen_una_sola_vez(): void
    {
        $codigo = $this->codigo();
        $pedido = $this->pedido($codigo);

        // Las dos se cargaron antes de que cualquiera consumiera: la marca se relee bajo lock.
        $a = Order::find($pedido->id);
        $b = Order::find($pedido->id);

        $this->servicio->consumirUso($a);
        $this->servicio->consumirUso($b);

        $this->assertSame(1, $codigo->fresh()->usage_count);
    }

    public function test_consumir_un_pedido_sin_codigo_no_hace_nada(): void
    {
        $pedido = $this->pedido(null);

        $this->servicio->consumirUso($pedido);

        $this->assertNull($pedido->fresh()->discount_usage_consumido_at);
    }

    public function test_consumir_un_codigo_agotado_lanza_y_no_deja_marca(): void
    {
        $codigo = $this->codigo(['usage_limit' => 1, 'usage_count' => 1]);
        $pedido = $this->pedido($codigo);

        try {
            $this->servicio->consumirUso($pedido);
            $this->fail('Tenía que lanzar DiscountCodeInvalidoException.');
        } catch (DiscountCodeInvalidoException) {
            $this->assertSame(1, $codigo->fresh()->usage_count);
            $this->assertNull($pedido->fresh()->discount_usage_consumido_at);
        }
    }

    public function test_un_reintento_sobre_un_pedido_ya_consumido_no_falla_aunque_el_codigo_se_haya_agotado(): void
    {
        $codigo = $this->codigo(['usage_limit' => 1]);
        $pedido = $this->pedido($codigo);

        $this->servicio->consumirUso($pedido); // consume el único uso: el código queda agotado
        $this->assertTrue($codigo->fresh()->agotado());

        $this->servicio->consumirUso($pedido);  // el reintento no lanza ni suma

        $this->assertSame(1, $codigo->fresh()->usage_count);
    }

    // --- liberar --------------------------------------------------------------------

    public function test_liberar_dos_veces_devuelve_un_solo_uso_y_deja_la_marca(): void
    {
        $codigo = $this->codigo(['usage_count' => 3]);
        $pedido = $this->pedido($codigo);

        $this->servicio->liberarUso($pedido);
        $this->servicio->liberarUso($pedido);
        $this->servicio->liberarUso(Order::find($pedido->id));

        $this->assertSame(2, $codigo->fresh()->usage_count);
        $this->assertNotNull($pedido->fresh()->discount_usage_liberado_at);
        $this->assertTrue($pedido->fresh()->discount_usage_repuesto, 'Se mantiene el booleano heredado.');
        $this->assertFalse($pedido->isDirty());
    }

    public function test_dos_instancias_viejas_del_mismo_pedido_liberan_una_sola_vez(): void
    {
        $codigo = $this->codigo(['usage_count' => 3]);
        $pedido = $this->pedido($codigo);

        $a = Order::find($pedido->id);
        $b = Order::find($pedido->id);

        $this->servicio->liberarUso($a);
        $this->servicio->liberarUso($b);

        $this->assertSame(2, $codigo->fresh()->usage_count);
    }

    public function test_un_pedido_viejo_ya_repuesto_por_el_booleano_heredado_no_se_libera_otra_vez(): void
    {
        $codigo = $this->codigo(['usage_count' => 3]);
        // Pedido anterior a las marcas de tiempo: liberado con el booleano, sin timestamp.
        $pedido = $this->pedido($codigo, ['discount_usage_repuesto' => true]);

        $this->servicio->liberarUso($pedido);

        $this->assertSame(3, $codigo->fresh()->usage_count);
        $this->assertNull($pedido->fresh()->discount_usage_liberado_at);
    }

    public function test_un_pedido_viejo_sin_marcas_se_puede_liberar_una_vez(): void
    {
        $codigo = $this->codigo(['usage_count' => 3]);
        $pedido = $this->pedido($codigo); // consumido antes de existir las marcas: ambas null

        $this->servicio->liberarUso($pedido);
        $this->servicio->liberarUso($pedido);

        $this->assertSame(2, $codigo->fresh()->usage_count);
    }

    public function test_liberar_nunca_baja_el_contador_de_cero(): void
    {
        $codigo = $this->codigo(['usage_count' => 0]);
        $pedido = $this->pedido($codigo);

        $this->servicio->liberarUso($pedido);

        $this->assertSame(0, $codigo->fresh()->usage_count);
    }

    public function test_liberar_un_pedido_sin_codigo_no_hace_nada(): void
    {
        $pedido = $this->pedido(null);

        $this->servicio->liberarUso($pedido);

        $this->assertNull($pedido->fresh()->discount_usage_liberado_at);
        $this->assertFalse($pedido->fresh()->discount_usage_repuesto);
    }

    public function test_liberar_no_toca_el_snapshot_del_codigo_en_el_pedido(): void
    {
        $codigo = $this->codigo(['usage_count' => 1]);
        $pedido = $this->pedido($codigo, ['discount_amount' => 250]);

        $this->servicio->liberarUso($pedido);

        $this->assertSame('VERANO10', $pedido->fresh()->discount_code);
        $this->assertEquals(250, $pedido->fresh()->discount_amount);
        $this->assertSame($codigo->id, $pedido->fresh()->discount_code_id);
    }

    // --- consumir + liberar ---------------------------------------------------------------

    public function test_consumir_despues_de_liberar_no_suma(): void
    {
        $codigo = $this->codigo(['usage_count' => 1]);
        $pedido = $this->pedido($codigo);

        $this->servicio->liberarUso($pedido);   // 1 → 0
        $this->servicio->consumirUso($pedido);  // evento fuera de orden: no vuelve a sumar

        $this->assertSame(0, $codigo->fresh()->usage_count);
        $this->assertNull($pedido->fresh()->discount_usage_consumido_at);
    }

    public function test_consumir_liberar_y_repetir_todo_deja_el_contador_en_el_valor_correcto(): void
    {
        $codigo = $this->codigo(['usage_count' => 2]);
        $pedido = $this->pedido($codigo);

        foreach (range(1, 3) as $_) {
            $this->servicio->consumirUso($pedido);
        }
        $this->assertSame(3, $codigo->fresh()->usage_count);

        foreach (range(1, 3) as $_) {
            $this->servicio->liberarUso($pedido);
        }
        $this->assertSame(2, $codigo->fresh()->usage_count);

        foreach (range(1, 3) as $_) {
            $this->servicio->consumirUso($pedido);
        }
        $this->assertSame(2, $codigo->fresh()->usage_count);
    }

    // --- en el flujo real --------------------------------------------------------------

    public function test_el_checkout_deja_la_marca_de_consumo_y_la_cancelacion_la_de_liberacion(): void
    {
        $product = Product::factory()->for(Category::factory())->create(['price' => 1000, 'stock' => 10]);
        $codigo = $this->codigo();

        $this->withSession(['cart' => [['product_id' => $product->id, 'quantity' => 2]], 'cart_discount_code' => 'VERANO10'])
            ->postJson(route('cart.whatsapp'), ['customer_data' => [
                'name' => 'Juana', 'lastname' => 'Pérez', 'dni' => '30111222', 'province' => 'buenos-aires',
                'city' => 'La Plata', 'postal_code' => '1900', 'phone' => '1122334455', 'email' => 'j@example.com',
            ]])->assertOk();

        $pedido = Order::sole();
        $this->assertNotNull($pedido->discount_usage_consumido_at);
        $this->assertNull($pedido->discount_usage_liberado_at);
        $this->assertSame(1, $codigo->fresh()->usage_count);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->patch(route('admin.orders.update-status', $pedido), ['estado' => 'cancelado'])->assertRedirect();

        $pedido->refresh();
        $this->assertNotNull($pedido->discount_usage_liberado_at);
        $this->assertTrue($pedido->discount_usage_repuesto);
        $this->assertSame(0, $codigo->fresh()->usage_count);

        // Un reintento de la liberación (p. ej. por una repetición hacia otro sistema) no devuelve otro uso.
        $this->servicio->liberarUso(Order::find($pedido->id));
        $this->assertSame(0, $codigo->fresh()->usage_count);
    }
}
