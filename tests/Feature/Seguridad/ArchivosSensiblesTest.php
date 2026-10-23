<?php

namespace Tests\Feature\Seguridad;

use App\Rukoz\SeguridadDelCentral;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Archivos sensibles (S.2 · A.10, A.11, B.3):
 *  - las reglas de bloqueo de public/.htaccess, probadas con sus propias expresiones contra una lista de rutas que SÍ y que NO se sirven;
 *  - el comando `chispasfrias:seguridad-verificar` que se corre en el hosting;
 *  - la advertencia de TLS hacia el Central.
 */
class ArchivosSensiblesTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<array{string, string}> [patrón PCRE, flags] de cada `RewriteRule "…" - [F…]` del .htaccess de public/. */
    private function reglasDeBloqueo(): array
    {
        $contenido = (string) file_get_contents(base_path('public/.htaccess'));

        preg_match_all('/^\s*RewriteRule\s+"([^"]+)"\s+-\s+\[([^\]]*\bF\b[^\]]*)\]/m', $contenido, $m, PREG_SET_ORDER);

        return array_map(fn (array $r) => [$r[1], $r[2]], $m);
    }

    private function bloqueada(string $ruta): bool
    {
        foreach ($this->reglasDeBloqueo() as [$patron, $flags]) {
            $modificador = str_contains($flags, 'NC') ? 'i' : '';

            if (preg_match('#'.str_replace('#', '\#', $patron).'#'.$modificador, $ruta) === 1) {
                return true;
            }
        }

        return false;
    }

    public function test_el_htaccess_tiene_reglas_de_bloqueo(): void
    {
        $this->assertGreaterThanOrEqual(5, count($this->reglasDeBloqueo()));
    }

    #[DataProvider('rutasProhibidas')]
    public function test_el_htaccess_bloquea(string $ruta): void
    {
        $this->assertTrue($this->bloqueada($ruta), "public/.htaccess no bloquea /{$ruta}");
    }

    public static function rutasProhibidas(): array
    {
        $rutas = [
            '.env', '.env.production', '.env.backup', '.env.local', '.git/config', '.git/HEAD', '.gitignore', '.htpasswd', '.htaccess', '.DS_Store',
            'images/products/.htaccess', 'docs/.env', 'docs/test-abc/.env',
            'storage/logs/laravel.log', 'storage/app/private/x.txt', 'storage/framework/sessions/abc', 'vendor/autoload.php', 'vendor/composer/installed.json',
            'composer.json', 'composer.lock', 'package.json', 'package-lock.json', 'artisan', 'phpunit.xml', 'vite.config.js', 'tailwind.config.js', 'README.md', 'contextoproyecto.md',
            'app/Models/User.php', 'config/app.php', 'config/rukoz.php', 'database/database.sqlite', 'database/migrations/x.php', 'routes/web.php', 'bootstrap/app.php',
            'resources/js/app.jsx', 'tests/TestCase.php', 'node_modules/x/index.js',
            'backup.sql', 'dump.SQL', 'db.sqlite', 'db.sqlite3', 'sitio.zip', 'public_html.tar.gz', 'respaldo.tgz', 'x.rar', 'x.7z', 'index.php.bak', 'config.old', 'index.php~', 'laravel.log', 'error.log', 'php.ini', 'deploy.sh', 'docker-compose.yml',
        ];

        return array_combine($rutas, array_map(fn ($r) => [$r], $rutas));
    }

    #[DataProvider('rutasPermitidas')]
    public function test_el_htaccess_no_bloquea_lo_legitimo(string $ruta): void
    {
        $this->assertFalse($this->bloqueada($ruta), "public/.htaccess bloquea /{$ruta}, que tiene que servirse");
    }

    public static function rutasPermitidas(): array
    {
        $rutas = [
            '', 'productos', 'productos/12', 'combos/3', 'carrito', 'carrito/checkout', 'contacto', 'servicios/chispas', 'login', 'admin/dashboard', 'admin/documents',
            'robots.txt', 'sitemap.xml', 'favicon.ico', '.well-known/acme-challenge/token', '.well-known/security.txt',
            'build/manifest.json', 'build/assets/app-4f2a1c.js', 'build/assets/app-9d8e7f.css', 'build/assets/Poppins.woff2',
            'images/chispas-frias-logo.png', 'images/products/1790965109_0_6ac00.png', 'images/products/1790976648_0_6ac02.jpeg', 'images/products/clip.mp4', 'images/products/a.webp',
            'videos/chispas-frias-hero-video.mp4', 'docs/pdfs/documento_1_abc.pdf', 'csp-report', 'rukoz/webhook', 'up',
        ];

        return array_combine(array_map(fn ($r) => $r === '' ? '(raíz)' : $r, $rutas), array_map(fn ($r) => [$r], $rutas));
    }

    public function test_el_htaccess_manda_las_cabeceras_basicas_para_lo_estatico(): void
    {
        $contenido = (string) file_get_contents(base_path('public/.htaccess'));

        foreach (['X-Content-Type-Options "nosniff"', 'X-Frame-Options "DENY"', 'Referrer-Policy "strict-origin-when-cross-origin"', 'Permissions-Policy'] as $cabecera) {
            $this->assertStringContainsString($cabecera, $contenido);
        }

        // Todo lo de mod_headers va dentro de <IfModule>: si el hosting no lo tiene, el sitio sigue andando.
        $this->assertMatchesRegularExpression('/<IfModule mod_headers\.c>[\s\S]*Header always set X-Content-Type-Options[\s\S]*<\/IfModule>/', $contenido);
    }

    public function test_el_htaccess_conserva_el_front_controller_de_laravel(): void
    {
        $contenido = (string) file_get_contents(base_path('public/.htaccess'));

        $this->assertStringContainsString('RewriteRule ^ index.php [L]', $contenido);
        $this->assertStringContainsString('Options -MultiViews -Indexes', $contenido);
    }

    // --- el comando -----------------------------------------------------------------------------------------------------------

    private function publicTemporal(): string
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'chispas-verificar-'.uniqid();
        File::ensureDirectoryExists($dir);
        $this->app->usePublicPath($dir);
        config(['tienda.imagenes.ruta' => '/images/products/', 'tienda.imagenes.url' => '/images/products/', 'documents.pdf_path' => 'docs/pdfs']);

        return $dir;
    }

    public function test_el_comando_marca_mal_la_configuracion_de_desarrollo_en_produccion(): void
    {
        $this->publicTemporal();
        config(['app.debug' => true]);

        $codigo = Artisan::call('chispasfrias:seguridad-verificar');

        $this->assertSame(1, $codigo);
        $salida = Artisan::output();
        $this->assertStringContainsString('APP_DEBUG', $salida);
        $this->assertStringContainsString('MAL', $salida);
    }

    public function test_el_comando_encuentra_un_env_olvidado_y_php_en_las_subidas_y_el_reparar_escribe_el_htaccess(): void
    {
        $dir = $this->publicTemporal();
        File::ensureDirectoryExists("$dir/images/products");
        file_put_contents("$dir/.env.production", 'APP_KEY=secreta');
        file_put_contents("$dir/images/products/shell.php", '<?php echo 1;');
        file_put_contents("$dir/respaldo.sql", '-- dump');
        File::ensureDirectoryExists("$dir/docs/test-123");

        Artisan::call('chispasfrias:seguridad-verificar');
        $salida = Artisan::output();

        $this->assertStringContainsString('Sensibles bajo public/', $salida);
        $this->assertStringContainsString('.env.production', $salida);
        $this->assertStringContainsString('respaldo.sql', $salida);
        $this->assertStringContainsString('shell.php', $salida);
        $this->assertStringContainsString('test-123', $salida);
        $this->assertFileDoesNotExist("$dir/images/products/.htaccess");

        Artisan::call('chispasfrias:seguridad-verificar', ['--reparar' => true]);

        $this->assertFileExists("$dir/images/products/.htaccess");
    }

    public function test_el_comando_prueba_desde_afuera_y_detecta_un_env_publico_y_php_ejecutable(): void
    {
        $this->publicTemporal();

        // Hosting mal armado: el .env se ve y el servidor ejecuta PHP en las subidas.
        Http::fake(function ($request) {
            $ruta = parse_url($request->url(), PHP_URL_PATH);

            if ($ruta === '/.env') {
                return Http::response('APP_KEY=base64:secreta', 200);
            }

            if (str_contains($ruta, 'prueba-seguridad-') && str_ends_with($ruta, '.php')) {
                // El «servidor» ejecuta el PHP: devuelve lo que el script imprime.
                preg_match("/echo '([^']+)'/", (string) file_get_contents(public_path('images/products/'.basename($ruta))), $m);

                return Http::response($m[1] ?? '', 200);
            }

            return Http::response('Forbidden', 403);
        });

        File::ensureDirectoryExists(public_path('images/products'));

        $this->assertSame(1, Artisan::call('chispasfrias:seguridad-verificar', ['--url' => 'https://sitio.test']));

        $salida = Artisan::output();
        $this->assertMatchesRegularExpression('#/\.env\s.*MAL.*responde 200#s', $salida);
        $this->assertStringContainsString('EJECUTA PHP', $salida);

        // La prueba no deja el archivo atrás.
        $this->assertSame([], glob(public_path('images/products/prueba-seguridad-*')) ?: []);
    }

    public function test_el_comando_da_ok_si_el_servidor_responde_403_y_no_ejecuta_php(): void
    {
        $dir = $this->publicTemporal();
        File::ensureDirectoryExists("$dir/images/products");
        \App\Support\CarpetaDeSubidas::proteger("$dir/images/products");
        \App\Support\CarpetaDeSubidas::proteger("$dir/docs/pdfs");
        config(['app.debug' => false, 'app.key' => 'base64:'.base64_encode(random_bytes(32)), 'app.url' => 'https://sitio.test', 'session.secure' => true, 'session.encrypt' => true, 'mail.default' => 'smtp']);
        $this->app['env'] = 'production';

        Http::fake(fn ($request) => Http::response(str_contains($request->url(), 'prueba-seguridad-') ? 'Forbidden' : 'No encontrado', str_contains($request->url(), 'prueba-seguridad-') ? 403 : 404));

        $this->assertSame(0, Artisan::call('chispasfrias:seguridad-verificar', ['--url' => 'https://sitio.test']));
        $this->assertStringContainsString('Todo en orden', Artisan::output());
    }

    // --- TLS hacia el Central -------------------------------------------------------------------------------------------------

    public function test_la_url_del_central_tiene_que_ser_https_en_produccion(): void
    {
        config(['rukoz.habilitada' => true]);
        $this->app['env'] = 'production';

        config(['rukoz.url' => 'http://sistema.rukoz.com.ar']);
        $this->assertStringContainsString('no usa https', (string) SeguridadDelCentral::advertenciaTls());

        config(['rukoz.url' => 'http://localhost:8010']);
        $this->assertNotNull(SeguridadDelCentral::advertenciaTls(), 'localhost por http no vale en producción');

        config(['rukoz.url' => 'https://sistema.rukoz.com.ar']);
        $this->assertNull(SeguridadDelCentral::advertenciaTls());
    }

    public function test_en_desarrollo_se_tolera_http_solo_hacia_el_propio_equipo(): void
    {
        config(['rukoz.habilitada' => true]);
        $this->app['env'] = 'local';

        config(['rukoz.url' => 'http://localhost:8010']);
        $this->assertNull(SeguridadDelCentral::advertenciaTls());

        config(['rukoz.url' => 'http://127.0.0.1:8010']);
        $this->assertNull(SeguridadDelCentral::advertenciaTls());

        config(['rukoz.url' => 'http://sistema.rukoz.com.ar']);
        $this->assertNotNull(SeguridadDelCentral::advertenciaTls(), 'un host remoto por http nunca es aceptable');
    }

    public function test_sin_integracion_no_hay_advertencia_de_tls(): void
    {
        config(['rukoz.habilitada' => false, 'rukoz.url' => 'http://inseguro.test']);

        $this->assertNull(SeguridadDelCentral::advertenciaTls());
    }
}
