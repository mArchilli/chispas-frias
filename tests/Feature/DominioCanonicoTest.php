<?php

namespace Tests\Feature;

use App\Models\Combo;
use App\Models\ComboItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * El dominio canónico es `chispasfrias.com` y vive en APP_URL: ningún archivo lo tiene escrito.
 */
class DominioCanonicoTest extends TestCase
{
    use RefreshDatabase;

    public function test_robots_txt_usa_app_url(): void
    {
        config(['app.url' => 'https://ejemplo.test/']);

        $respuesta = $this->get('/robots.txt');

        $respuesta->assertOk();
        $this->assertStringStartsWith('text/plain', $respuesta->headers->get('Content-Type'));
        $this->assertStringContainsString('Disallow: /admin', $respuesta->getContent());
        $this->assertStringContainsString("Sitemap: https://ejemplo.test/sitemap.xml\n", $respuesta->getContent());
    }

    public function test_sitemap_lista_las_paginas_y_solo_productos_y_combos_activos(): void
    {
        config(['app.url' => 'https://ejemplo.test']);

        $activo = Product::factory()->create(['is_active' => true]);
        $inactivo = Product::factory()->create(['is_active' => false]);
        $combo = Combo::create(['title' => 'C', 'price' => 1, 'is_active' => true]);
        $comboInactivo = Combo::create(['title' => 'D', 'price' => 1, 'is_active' => false]);
        ComboItem::create(['combo_id' => $combo->id, 'product_id' => $activo->id, 'quantity' => 1, 'sort_order' => 0]);

        $respuesta = $this->get('/sitemap.xml');

        $respuesta->assertOk();
        $this->assertStringStartsWith('application/xml', $respuesta->headers->get('Content-Type'));
        $xml = $respuesta->getContent();
        $this->assertStringContainsString('<loc>https://ejemplo.test/</loc>', $xml);
        $this->assertStringContainsString('<loc>https://ejemplo.test/productos</loc>', $xml);
        $this->assertStringContainsString("<loc>https://ejemplo.test/productos/{$activo->id}</loc>", $xml);
        $this->assertStringNotContainsString("/productos/{$inactivo->id}<", $xml);
        $this->assertStringContainsString("<loc>https://ejemplo.test/combos/{$combo->id}</loc>", $xml);
        $this->assertStringNotContainsString("/combos/{$comboInactivo->id}<", $xml);
        $this->assertNotFalse(simplexml_load_string($xml), 'El sitemap tiene que ser XML válido');
    }

    public function test_la_url_de_la_tienda_se_comparte_a_las_paginas_inertia(): void
    {
        config(['app.url' => 'https://ejemplo.test/']);

        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('tienda.url', 'https://ejemplo.test'));
    }

    public function test_ningun_archivo_del_codigo_tiene_el_dominio_escrito(): void
    {
        $encontrados = [];

        foreach (['app', 'routes', 'resources', 'config', 'database', 'public', 'bootstrap'] as $carpeta) {
            if (! is_dir(base_path($carpeta))) {
                continue;
            }

            foreach (File::allFiles(base_path($carpeta)) as $archivo) {
                $ruta = str_replace('\\', '/', $archivo->getPathname());

                if (str_contains($ruta, '/public/build/') || ! preg_match('/\.(php|jsx?|txt|xml|html|json)$/', $ruta)) {
                    continue;
                }

                if (preg_match('/chispas-?frias\.com/i', (string) file_get_contents($archivo->getPathname()))) {
                    $encontrados[] = str_replace(str_replace('\\', '/', base_path()).'/', '', $ruta);
                }
            }
        }

        $this->assertSame([], $encontrados, 'El dominio tiene que salir de APP_URL, no estar escrito en estos archivos');
    }

    public function test_env_example_apunta_al_dominio_canonico(): void
    {
        $this->assertStringContainsString("\nAPP_URL=https://chispasfrias.com\n", file_get_contents(base_path('.env.example')));
        $this->assertStringNotContainsString('chispasfrias.com.ar', file_get_contents(base_path('.env.example')));
    }
}
