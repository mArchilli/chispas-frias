<?php

namespace Tests\Feature\Seguridad;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * En producción, http:// redirige a https:// (middleware RedirigirAHttps, global y antes de la sesión). Ver docs/SEGURIDAD.md.
 */
class RedireccionHttpsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function enProduccion(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
    }

    public function test_http_redirige_con_301_a_la_misma_url_en_https_conservando_path_y_query(): void
    {
        $this->enProduccion();

        $respuesta = $this->get('http://tienda.test/productos/lista?color=rojo&page=2&q=a%20b');

        $respuesta->assertStatus(301);
        $this->assertSame('https://tienda.test/productos/lista?color=rojo&page=2&q=a%20b', $respuesta->headers->get('Location'));
    }

    public function test_la_raiz_tambien_redirige(): void
    {
        $this->enProduccion();

        $respuesta = $this->get('http://tienda.test/');

        $respuesta->assertStatus(301);
        $this->assertSame('https://tienda.test/', $respuesta->headers->get('Location'));
    }

    public function test_https_no_se_redirige(): void
    {
        $this->enProduccion();

        $respuesta = $this->get('https://tienda.test/contacto');

        $respuesta->assertOk();
        $this->assertNull($respuesta->headers->get('Location'));
    }

    public function test_el_redirect_no_crea_sesion_ni_cookies(): void
    {
        $this->enProduccion();

        $respuesta = $this->get('http://tienda.test/contacto');

        $respuesta->assertStatus(301);
        $this->assertSame([], $respuesta->headers->getCookies(), 'un pedido http no tiene que llegar a la sesión ni dejar cookies');
    }

    public function test_los_metodos_que_no_son_get_reciben_308_para_conservar_el_metodo(): void
    {
        $this->enProduccion();

        $respuesta = $this->post('http://tienda.test/login', ['email' => 'a@b.c', 'password' => 'x']);

        $respuesta->assertStatus(308);
        $this->assertSame('https://tienda.test/login', $respuesta->headers->get('Location'));
    }

    public function test_conserva_el_host_y_descarta_el_puerto_de_http(): void
    {
        $this->enProduccion();

        $respuesta = $this->get('http://www.tienda.test:8080/contacto');

        $respuesta->assertStatus(301);
        $this->assertSame('https://www.tienda.test/contacto', $respuesta->headers->get('Location'));
    }

    public function test_up_no_se_redirige(): void
    {
        $this->enProduccion();

        $this->get('http://tienda.test/up')->assertOk();
    }

    public function test_csp_report_no_se_redirige(): void
    {
        $this->enProduccion();

        $respuesta = $this->postJson('http://tienda.test/csp-report', ['csp-report' => ['violated-directive' => 'img-src']]);

        $this->assertNotContains($respuesta->getStatusCode(), [301, 308]);
        $this->assertNull($respuesta->headers->get('Location'));
    }

    public function test_well_known_no_se_redirige(): void
    {
        $this->enProduccion();

        // La ruta no existe en la app (el desafío del certificado lo sirve un archivo): lo que importa es que no sea una redirección.
        $respuesta = $this->get('http://tienda.test/.well-known/acme-challenge/abc123');

        $this->assertNotContains($respuesta->getStatusCode(), [301, 302, 308]);
        $this->assertNull($respuesta->headers->get('Location'));
    }

    public function test_fuera_de_produccion_no_redirige(): void
    {
        // El entorno de los tests es «testing».
        $this->assertFalse(app()->isProduction());

        $this->get('http://tienda.test/contacto')->assertOk();
    }

    public function test_se_puede_apagar_con_seguridad_forzar_https(): void
    {
        $this->enProduccion();
        config(['seguridad.forzar_https' => false]);

        $this->get('http://tienda.test/contacto')->assertOk();
    }

    public function test_el_middleware_esta_registrado_como_global_y_no_en_el_grupo_web(): void
    {
        $kernel = app(\Illuminate\Contracts\Http\Kernel::class);

        $this->assertTrue($kernel->hasMiddleware(\App\Http\Middleware\RedirigirAHttps::class));
        $this->assertNotContains(\App\Http\Middleware\RedirigirAHttps::class, $kernel->getMiddlewareGroups()['web'] ?? []);
        $this->assertNotContains(\App\Http\Middleware\RedirigirAHttps::class, $kernel->getRouteMiddleware());
    }
}
