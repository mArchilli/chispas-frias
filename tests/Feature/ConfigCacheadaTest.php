<?php

namespace Tests\Feature;

use App\Models\ComboImage;
use App\Models\ProductImage;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Con `php artisan config:cache` (lo normal en producción) env() devuelve null fuera de
 * config/: la carpeta y la URL de las imágenes tenían que salir del .env y se rompían. Estos
 * tests fijan que todo se lee con config() y que, con la configuración cacheada y sin .env,
 * los valores llegan igual.
 */
class ConfigCacheadaTest extends TestCase
{
    /** Archivo de caché de prueba (relativo a la base del proyecto): no pisa bootstrap/cache/config.php. */
    private const CACHE_PRUEBA = 'storage/framework/cache/config-cacheada-test.php';

    /** Barras y dobles barras iguales: en Windows public_path() mezcla "\" y "/". */
    private function normalizar(string $ruta): string
    {
        return preg_replace('#/+#', '/', str_replace('\\', '/', $ruta));
    }

    public function test_no_hay_env_fuera_de_config(): void
    {
        $archivos = Finder::create()
            ->files()
            ->in([base_path('app'), base_path('routes'), base_path('bootstrap'), base_path('database'), base_path('resources/views')])
            ->name('*.php')
            ->notPath('cache');

        $usos = [];

        foreach ($archivos as $archivo) {
            if (preg_match('/(?<![\w>:$])env\s*\(/', $archivo->getContents())) {
                $usos[] = $archivo->getRelativePathname();
            }
        }

        $this->assertSame([], $usos, 'env() solo puede usarse en config/. Usá config().');
    }

    public function test_las_imagenes_leen_la_ruta_y_la_url_desde_config(): void
    {
        config(['tienda.imagenes.ruta' => '/../../public_html/images/products/', 'tienda.imagenes.url' => '/cdn/products/']);

        $imagen = new ProductImage(['path' => 'foto.jpg']);
        $combo = new ComboImage(['path' => 'combo.jpg']);

        $this->assertSame('/cdn/products/foto.jpg', $imagen->url);
        $this->assertSame('/cdn/products/combo.jpg', $combo->url);
        $this->assertSame(
            $this->normalizar(public_path('../../public_html/images/products').'/foto.jpg'),
            $this->normalizar($imagen->getFilesystemPath())
        );
        // Un path legacy (ya trae la ruta web) se usa tal cual, sin pasar por la config.
        $this->assertSame('/images/products/viejo.jpg', (new ProductImage(['path' => '/images/products/viejo.jpg']))->url);
    }

    public function test_con_la_config_cacheada_y_sin_env_los_valores_llegan_igual(): void
    {
        $cache = base_path(self::CACHE_PRUEBA);

        $entorno = [
            'APP_CONFIG_CACHE' => self::CACHE_PRUEBA,
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
        ];

        try {
            // 1) Se cachea la configuración con valores propios en el entorno del proceso.
            $cachear = new Process([PHP_BINARY, 'artisan', 'config:cache'], base_path(), $entorno + [
                'PRODUCT_IMAGES_PATH' => '/carpeta/propia/',
                'VITE_PRODUCT_IMAGES_PATH' => '/cdn/propio/',
                'WHATSAPP_NUMERO' => '5491100000000',
            ]);
            $cachear->mustRun();
            $this->assertFileExists($cache);

            // 2) Proceso nuevo, con la config cacheada y SIN esas variables ni el .env: lo
            //    único que puede dar los valores es config().
            $leer = new Process([
                PHP_BINARY, 'artisan', 'tinker', '--execute',
                'echo json_encode(['
                ."'ruta' => config('tienda.imagenes.ruta'),"
                ."'url' => config('tienda.imagenes.url'),"
                ."'whatsapp' => config('tienda.whatsapp'),"
                ."'imagen_url' => (new App\\Models\\ProductImage(['path' => 'a.jpg']))->url,"
                ."'combo_url' => (new App\\Models\\ComboImage(['path' => 'a.jpg']))->url,"
                ."'imagen_dir' => (new App\\Models\\ProductImage(['path' => 'a.jpg']))->getFilesystemPath(),"
                .']);',
            ], base_path(), $entorno + [
                'PRODUCT_IMAGES_PATH' => false,
                'VITE_PRODUCT_IMAGES_PATH' => false,
                'WHATSAPP_NUMERO' => false,
            ]);
            $leer->mustRun();

            $resultado = json_decode(trim($leer->getOutput()), true);

            $this->assertIsArray($resultado, 'Salida inesperada: '.$leer->getOutput());
            $this->assertSame('/carpeta/propia/', $resultado['ruta']);
            $this->assertSame('/cdn/propio/', $resultado['url']);
            $this->assertSame('5491100000000', $resultado['whatsapp']);
            $this->assertSame('/cdn/propio/a.jpg', $resultado['imagen_url']);
            $this->assertSame('/cdn/propio/a.jpg', $resultado['combo_url']);
            $this->assertSame(
                $this->normalizar(public_path('carpeta/propia').'/a.jpg'),
                $this->normalizar($resultado['imagen_dir'])
            );
        } finally {
            if (is_file($cache)) {
                unlink($cache);
            }
        }
    }
}
