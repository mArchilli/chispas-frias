<?php

namespace Tests\Support;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Rukoz\EspejoStock;
use App\Rukoz\EstadoDesdeCentral;
use Illuminate\Support\Facades\Event;
use Rukoz\CoreClient\Eventos\PedidoActualizado;
use Rukoz\CoreClient\Eventos\StockActualizado;

/**
 * Lo que comparten los tests de la 10.4: un Central falso con la confirmación encendida, pedidos hechos por el checkout REAL
 * (reservan en el Central) y la confirmación por la ruta real del admin.
 *
 * Los listeners se registran a mano: la config de los tests tiene los interruptores apagados al arrancar la aplicación, así que
 * `AppServiceProvider` no los registra.
 */
trait PedidosConCentral
{
    private CentralDePrueba $central;

    protected function prepararCentral(): void
    {
        $this->central = CentralDePrueba::instalar();
        config(['integracion.confirmacion' => true]);

        Event::listen(StockActualizado::class, [EspejoStock::class, 'alActualizarse']);
        Event::listen(PedidoActualizado::class, [EstadoDesdeCentral::class, 'alActualizarse']);
    }

    protected function exigirContratoSinErrores(): void
    {
        $this->assertSame([], $this->central->erroresDeContrato, 'La web mandó un body que el Central rechazaría');
    }

    protected function producto(int $precio = 1000, int $stock = 50): Product
    {
        return Product::factory()->for(Category::factory())->create(['title' => 'Chispa', 'price' => $precio, 'stock' => $stock, 'is_active' => true]);
    }

    /** Un pedido hecho por el checkout real: reservó en el Central, no tocó el stock local. */
    protected function comprar(int $cantidad = 2, int $precio = 1000, array $sesion = [], ?Product $producto = null): Order
    {
        $producto ??= $this->producto($precio);

        $respuesta = $this->withSession(array_merge(['cart' => [['product_id' => $producto->id, 'quantity' => $cantidad]]], $sesion))
            ->postJson(route('cart.whatsapp'), ['customer_data' => [
                'name' => 'Cliente', 'lastname' => 'De prueba', 'dni' => '30111222', 'province' => 'buenos-aires', 'city' => 'La Plata',
                'postal_code' => '1900', 'phone' => '1122334455', 'email' => 'cliente@example.com',
                'observations' => 'Nota original',
            ]])->assertOk();

        return Order::findOrFail($respuesta->json('order_id'));
    }

    /** Un pedido de los de siempre (sin `rukoz_ref`, stock local), hecho antes de encender el checkout. */
    protected function comprarSinCentral(int $cantidad = 2): Order
    {
        config(['integracion.checkout' => false]);
        $pedido = $this->comprar($cantidad);
        config(['integracion.checkout' => true]);

        return $pedido;
    }

    protected function vendedor(array $extra = []): User
    {
        return User::factory()->vendedor()->create(['rukoz_vendedor_id' => '7', ...$extra]);
    }

    protected function admin(array $extra = []): User
    {
        return User::factory()->admin()->create($extra);
    }

    /** @return array<string, mixed> */
    protected function envio(array $extra = []): array
    {
        return ['forma_envio_id' => 1, 'costo_cobrado' => 5000, 'es_gratis' => false, 'transportista_id' => 1, ...$extra];
    }

    protected function confirmar(Order $pedido, array $datos = [], ?User $usuario = null)
    {
        return $this->actingAs($usuario ?? $this->vendedor())
            ->post(route('admin.orders.confirm', $pedido), ['pagos' => [], 'envio' => $this->envio(), ...$datos]);
    }

    /** Un pedido ya confirmado desde la web (con un pago de 500). */
    protected function confirmado(int $cantidad = 2, array $sesion = [], array $datos = []): Order
    {
        $pedido = $this->comprar($cantidad, 1000, $sesion);

        $this->confirmar($pedido, ['pagos' => [['cuenta' => 'EFECTIVO', 'monto' => 500]], 'envio' => $this->envio(['costo_cobrado' => 0]), ...$datos])
            ->assertSessionHasNoErrors();

        return $pedido->fresh();
    }

    protected function cambiarEstado(Order $pedido, string $estado, ?User $usuario = null)
    {
        return $this->actingAs($usuario ?? $this->admin())
            ->patch(route('admin.orders.update-status', $pedido), ['estado' => $estado]);
    }

    /** @return list<array{0: string, 1: string, 2: array}> */
    protected function posts(): array
    {
        return $this->central->llamadasA('POST', '#^/pedidos/[^/]+/confirmar$#');
    }
}
