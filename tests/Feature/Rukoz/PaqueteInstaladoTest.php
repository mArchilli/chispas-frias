<?php

namespace Tests\Feature\Rukoz;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * El paquete rukoz/core-client viene de GitHub (VCS), está apagado por defecto y, encendido, registra su
 * webhook fuera del grupo `web` (sin sesión ni CSRF).
 */
class PaqueteInstaladoTest extends TestCase
{
    public function test_composer_instala_el_paquete_desde_github_sin_rutas_locales(): void
    {
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);
        $this->assertSame('^0.1.2', $composer['require']['rukoz/core-client']);
        $this->assertSame('vcs', collect($composer['repositories'])->firstWhere('url', 'https://github.com/mArchilli/rukoz-core-client.git')['type']);
        $this->assertNull(collect($composer['repositories'])->firstWhere('type', 'path'), 'No se usan repositorios por ruta local');

        $lock = json_decode((string) file_get_contents(base_path('composer.lock')), true);
        $paquete = collect($lock['packages'])->firstWhere('name', 'rukoz/core-client');
        $this->assertSame('0.1.2', $paquete['version']);
        $this->assertStringContainsString('github.com', $paquete['source']['url']);
        $this->assertStringContainsString('github.com', $paquete['dist']['url']);
        $this->assertStringNotContainsString('Desktop', (string) file_get_contents(base_path('composer.lock')));
    }

    public function test_apagado_por_defecto_no_registra_el_webhook(): void
    {
        $this->assertFalse((bool) config('rukoz.habilitada'));
        $this->assertNull(Route::getRoutes()->getByName('rukoz.webhook'));
    }

    public function test_encendido_el_webhook_queda_fuera_de_web_y_de_csrf(): void
    {
        $anterior = getenv('RUKOZ_INTEGRACION');
        putenv('RUKOZ_INTEGRACION=true');
        $_ENV['RUKOZ_INTEGRACION'] = $_SERVER['RUKOZ_INTEGRACION'] = 'true';

        try {
            $this->refreshApplication();

            $ruta = Route::getRoutes()->getByName('rukoz.webhook');
            $this->assertNotNull($ruta, 'El paquete registra POST /rukoz/webhook con la integración encendida');
            $this->assertSame(['POST'], $ruta->methods());
            $this->assertSame('rukoz/webhook', $ruta->uri());
            $this->assertNotContains('web', $ruta->gatherMiddleware());

            // Sin sesión ni token CSRF no es un 419: el paquete responde por la firma (rechaza sin firma válida).
            $respuesta = $this->postJson('/rukoz/webhook', ['evento' => 'x']);
            $this->assertNotSame(419, $respuesta->status());
            $this->assertContains($respuesta->status(), [400, 401, 403, 503]);
        } finally {
            $anterior === false ? putenv('RUKOZ_INTEGRACION') : putenv('RUKOZ_INTEGRACION='.$anterior);
            unset($_ENV['RUKOZ_INTEGRACION'], $_SERVER['RUKOZ_INTEGRACION']);
            $this->refreshApplication();
        }
    }
}
