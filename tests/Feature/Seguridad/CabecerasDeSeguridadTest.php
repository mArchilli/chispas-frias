<?php

namespace Tests\Feature\Seguridad;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CabecerasDeSeguridadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_toda_respuesta_lleva_las_cabeceras_basicas(): void
    {
        $respuesta = $this->get('/contacto');

        $respuesta->assertOk();
        $respuesta->assertHeader('X-Frame-Options', 'DENY');
        $respuesta->assertHeader('X-Content-Type-Options', 'nosniff');
        $respuesta->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString('camera=()', $respuesta->headers->get('Permissions-Policy'));
    }

    public function test_la_csp_arranca_en_modo_solo_reportar_y_no_bloquea(): void
    {
        $respuesta = $this->get('/contacto');

        $this->assertNull($respuesta->headers->get('Content-Security-Policy'));
        $politica = $respuesta->headers->get('Content-Security-Policy-Report-Only');

        $this->assertNotNull($politica);
        $this->assertStringContainsString("default-src 'self'", $politica);
        $this->assertStringContainsString("object-src 'none'", $politica);
        $this->assertStringContainsString("frame-ancestors 'none'", $politica);
        $this->assertStringContainsString("base-uri 'self'", $politica);
        $this->assertStringContainsString("form-action 'self'", $politica);
        $this->assertStringContainsString('report-uri /csp-report', $politica);
    }

    public function test_la_csp_no_permite_js_inline_sin_nonce_ni_eval_ni_scripts_ajenos(): void
    {
        $politica = $this->get('/contacto')->headers->get('Content-Security-Policy-Report-Only');

        preg_match("/script-src ([^;]+)/", $politica, $m);

        $this->assertMatchesRegularExpression("/'nonce-[A-Za-z0-9+\/=]+'/", $m[1]);
        $this->assertStringNotContainsString('unsafe-inline', $m[1]);
        $this->assertStringNotContainsString('unsafe-eval', $m[1]);
        $this->assertStringNotContainsString('*', $m[1]);
        $this->assertStringNotContainsString('http', $m[1]);
    }

    public function test_solo_se_permite_la_fuente_de_bunny_como_tercero(): void
    {
        $politica = $this->get('/contacto')->headers->get('Content-Security-Policy-Report-Only');

        $this->assertStringContainsString('style-src', $politica);
        $this->assertSame(
            ['https://fonts.bunny.net'],
            array_values(array_unique(array_filter(
                preg_split('/[\s;]+/', $politica),
                fn (string $parte) => str_starts_with($parte, 'https://'),
            ))),
        );
    }

    public function test_el_modo_enforce_manda_la_cabecera_que_bloquea(): void
    {
        config(['seguridad.csp.modo' => 'enforce']);

        $respuesta = $this->get('/contacto');

        $this->assertNotNull($respuesta->headers->get('Content-Security-Policy'));
        $this->assertNull($respuesta->headers->get('Content-Security-Policy-Report-Only'));
    }

    public function test_el_modo_off_no_manda_csp_pero_si_el_resto(): void
    {
        config(['seguridad.csp.modo' => 'off']);

        $respuesta = $this->get('/contacto');

        $this->assertNull($respuesta->headers->get('Content-Security-Policy'));
        $this->assertNull($respuesta->headers->get('Content-Security-Policy-Report-Only'));
        $respuesta->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_los_origenes_extra_del_env_se_suman_a_la_politica(): void
    {
        config(['seguridad.csp.extra_script' => ['https://www.googletagmanager.com'], 'seguridad.csp.extra_img' => ['https://www.google-analytics.com']]);

        $politica = $this->get('/contacto')->headers->get('Content-Security-Policy-Report-Only');

        $this->assertMatchesRegularExpression('#script-src [^;]*https://www\.googletagmanager\.com#', $politica);
        $this->assertMatchesRegularExpression('#img-src [^;]*https://www\.google-analytics\.com#', $politica);
    }

    public function test_el_script_inline_de_ziggy_lleva_el_mismo_nonce_que_la_csp(): void
    {
        $respuesta = $this->get('/contacto');

        preg_match("/'nonce-([^']+)'/", $respuesta->headers->get('Content-Security-Policy-Report-Only'), $csp);
        $this->assertNotEmpty($csp[1] ?? null);

        $this->assertMatchesRegularExpression('/<script[^>]*nonce="'.preg_quote($csp[1], '/').'"[^>]*>(const Ziggy=|Object\.assign\(Ziggy)/', $respuesta->getContent());
    }

    public function test_hsts_solo_con_https_y_sin_subdominios_por_defecto(): void
    {
        $this->assertNull($this->get('http://chispasfrias.test/contacto')->headers->get('Strict-Transport-Security'));

        $seguro = $this->get('https://chispasfrias.test/contacto');

        $this->assertSame('max-age=31536000', $seguro->headers->get('Strict-Transport-Security'));
        $this->assertStringContainsString('upgrade-insecure-requests', $seguro->headers->get('Content-Security-Policy-Report-Only'));

        config(['seguridad.hsts.subdominios' => true]);
        $this->assertSame('max-age=31536000; includeSubDomains', $this->get('https://chispasfrias.test/contacto')->headers->get('Strict-Transport-Security'));
    }

    public function test_el_panel_y_el_login_no_se_indexan_pero_la_tienda_si(): void
    {
        $this->assertStringContainsString('noindex', (string) $this->get('/login')->headers->get('X-Robots-Tag'));
        $this->assertNull($this->get('/contacto')->headers->get('X-Robots-Tag'));
    }

    // --- /csp-report ---------------------------------------------------------------------------------------------------------

    public function test_el_reporte_de_la_csp_responde_204_sin_csrf_y_guarda_solo_lo_necesario(): void
    {
        $archivo = storage_path('logs/seguridad.log');
        File::delete($archivo);

        $respuesta = $this->call('POST', '/csp-report', [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], json_encode(['csp-report' => [
            'document-uri' => 'https://chispasfrias.com/carrito/checkout?email=juan@ejemplo.com&dni=30123456',
            'blocked-uri' => 'https://tracker.ejemplo.net/pixel.gif?uid=30123456',
            'effective-directive' => 'img-src',
            'original-policy' => "default-src 'self'",
        ]]));

        $respuesta->assertNoContent();

        $log = (string) @file_get_contents($archivo);
        $this->assertStringContainsString('img-src', $log);
        $this->assertStringContainsString('https://tracker.ejemplo.net', $log);
        $this->assertStringContainsString('/carrito/checkout', $log);
        // Sin la query de las URLs: puede traer datos de personas.
        $this->assertStringNotContainsString('juan@ejemplo.com', $log);
        $this->assertStringNotContainsString('30123456', $log);

        File::delete($archivo);
    }

    public function test_el_reporte_de_la_csp_ignora_cuerpos_grandes_o_rotos(): void
    {
        $archivo = storage_path('logs/seguridad.log');
        File::delete($archivo);

        $this->call('POST', '/csp-report', [], [], [], [], str_repeat('A', 10000))->assertNoContent();
        $this->call('POST', '/csp-report', [], [], [], [], '{no es json')->assertNoContent();

        $this->assertFileDoesNotExist($archivo);
    }

    public function test_el_reporte_de_la_csp_tiene_limite_por_ip(): void
    {
        config(['seguridad.limites.csp_report_por_minuto' => 3]);

        for ($i = 0; $i < 3; $i++) {
            $this->call('POST', '/csp-report', [], [], [], [], '{}')->assertNoContent();
        }

        $this->call('POST', '/csp-report', [], [], [], [], '{}')->assertStatus(429);
    }
}
