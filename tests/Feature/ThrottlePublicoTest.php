<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * El checkout y la validación de cupones tienen un límite de requests por minuto, por IP,
 * configurable en config/tienda.php. Al superarlo el cliente recibe un mensaje amable.
 */
class ThrottlePublicoTest extends TestCase
{
    use RefreshDatabase;

    private function intentarCheckout(string $ip = '10.0.0.1')
    {
        // Carrito vacío: responde 422 sin tocar la base, pero igual cuenta para el límite.
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson(route('cart.whatsapp'), ['customer_data' => ['name' => 'x']]);
    }

    private function intentarCupon(string $codigo = 'NOEXISTE', string $ip = '10.0.0.1', bool $json = true)
    {
        $request = $this->withServerVariables(['REMOTE_ADDR' => $ip]);

        return $json
            ? $request->postJson(route('cart.discount.apply'), ['code' => $codigo])
            : $request->post(route('cart.discount.apply'), ['code' => $codigo]);
    }

    public function test_los_limites_por_defecto_son_razonables(): void
    {
        $this->assertSame(10, config('tienda.limites.checkout_por_minuto'));
        $this->assertSame(30, config('tienda.limites.validar_cupon_por_minuto'));
        $this->assertNotSame('', config('tienda.limites.mensaje'));
    }

    // --- checkout -----------------------------------------------------------------------

    public function test_el_checkout_responde_429_con_un_mensaje_amable_al_superar_el_limite(): void
    {
        config(['tienda.limites.checkout_por_minuto' => 3]);

        foreach (range(1, 3) as $_) {
            $this->intentarCheckout()->assertStatus(422); // todavía pasa (carrito vacío)
        }

        $this->intentarCheckout()
            ->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', config('tienda.limites.mensaje'))
            ->assertHeader('Retry-After');
    }

    public function test_el_limite_del_checkout_es_por_ip(): void
    {
        config(['tienda.limites.checkout_por_minuto' => 2]);

        $this->intentarCheckout('10.0.0.1');
        $this->intentarCheckout('10.0.0.1');
        $this->intentarCheckout('10.0.0.1')->assertStatus(429);

        // Otra persona, otra IP: no se ve afectada.
        $this->intentarCheckout('10.0.0.2')->assertStatus(422);
    }

    public function test_el_limite_del_checkout_se_configura_por_env(): void
    {
        config(['tienda.limites.checkout_por_minuto' => 1]);

        $this->intentarCheckout()->assertStatus(422);
        $this->intentarCheckout()->assertStatus(429);

        RateLimiter::clear('checkout');
    }

    public function test_un_checkout_normal_dentro_del_limite_sigue_funcionando(): void
    {
        $producto = \App\Models\Product::factory()->for(\App\Models\Category::factory())->create(['price' => 1000, 'stock' => 5]);

        $this->withSession(['cart' => [['product_id' => $producto->id, 'quantity' => 1]]])
            ->postJson(route('cart.whatsapp'), ['customer_data' => [
                'name' => 'Juana Pérez', 'province' => 'buenos-aires', 'phone' => '1122334455',
            ]])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    // --- validación de cupones -----------------------------------------------------------

    public function test_la_validacion_de_cupones_responde_429_al_superar_el_limite(): void
    {
        config(['tienda.limites.validar_cupon_por_minuto' => 2]);

        $this->intentarCupon('A')->assertStatus(422);
        $this->intentarCupon('B')->assertStatus(422);

        $this->intentarCupon('C')
            ->assertStatus(429)
            ->assertJsonPath('message', config('tienda.limites.mensaje'))
            ->assertHeader('Retry-After');
    }

    public function test_el_limite_de_cupones_no_afecta_al_del_checkout(): void
    {
        config(['tienda.limites.validar_cupon_por_minuto' => 1, 'tienda.limites.checkout_por_minuto' => 5]);

        $this->intentarCupon('A');
        $this->intentarCupon('B')->assertStatus(429);

        $this->intentarCheckout()->assertStatus(422);
    }

    public function test_una_navegacion_normal_recibe_un_aviso_en_vez_de_un_429_pelado(): void
    {
        config(['tienda.limites.validar_cupon_por_minuto' => 1]);

        $this->intentarCupon('A', json: false);

        $this->from(route('cart.index'))
            ->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->post(route('cart.discount.apply'), ['code' => 'B'])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('error', config('tienda.limites.mensaje'));
    }

    public function test_las_rutas_del_carrito_que_no_escriben_no_tienen_limite(): void
    {
        config(['tienda.limites.checkout_por_minuto' => 1, 'tienda.limites.validar_cupon_por_minuto' => 1]);

        foreach (range(1, 5) as $_) {
            $this->get(route('cart.preview'))->assertOk();
        }
    }
}
