<?php

namespace Tests\Feature\Seguridad;

use App\Models\Category;
use App\Models\User;
use App\Support\CarpetaDeSubidas;
use FilesystemIterator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Uploads del panel (S.2 · A.6): el tipo sale del contenido, el nombre es aleatorio, nunca queda algo ejecutable y la carpeta lleva un
 * .htaccess que no deja correr PHP. Como el trait escribe bajo public_path(), se redirige a una carpeta temporal del sistema.
 */
class SubidasSegurasTest extends TestCase
{
    use RefreshDatabase;

    private string $publicTemporal;

    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        $this->publicTemporal = sys_get_temp_dir().DIRECTORY_SEPARATOR.'chispas-subidas-'.uniqid();
        File::ensureDirectoryExists($this->publicTemporal);
        $this->app->usePublicPath($this->publicTemporal);
        $this->withoutVite();

        config(['tienda.imagenes.ruta' => '/images/products/', 'documents.pdf_path' => 'docs/pdfs']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        gc_collect_cycles();
        self::borrar($this->publicTemporal);
    }

    private static function borrar(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        set_error_handler(fn () => true);

        try {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($it as $entrada) {
                $entrada->isDir() ? rmdir($entrada->getPathname()) : unlink($entrada->getPathname());
            }
            unset($it);
            rmdir($dir);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Un archivo subido de verdad: con `UploadedFile::fake()` Laravel deduce el tipo del NOMBRE, no del contenido, y no probaría nada.
     * Acá el tipo lo detecta finfo leyendo los bytes, como en producción.
     */
    private function archivo(string $nombreCliente, string $contenido): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'subida');
        file_put_contents($ruta, $contenido);

        return new UploadedFile($ruta, $nombreCliente, null, null, true);
    }

    /** @return list<string> nombres de archivo (no .htaccess) en la carpeta de imágenes. */
    private function archivosGuardados(): array
    {
        $dir = $this->publicTemporal.'/images/products';

        return is_dir($dir) ? array_values(array_diff(scandir($dir), ['.', '..', '.htaccess'])) : [];
    }

    private function crearProducto(array $archivos)
    {
        $admin = User::factory()->admin()->create();

        return $this->actingAs($admin)->post(route('admin.products.store'), [
            'title' => 'Producto de prueba',
            'description' => '<p>x</p>',
            'price' => 100,
            'category_id' => Category::factory()->create()->id,
            'images' => $archivos,
        ]);
    }

    public function test_una_imagen_real_se_guarda_con_nombre_aleatorio_y_extension_por_contenido_aunque_el_nombre_diga_otra_cosa(): void
    {
        $archivo = $this->archivo('foto.jpg', base64_decode(self::PNG_1X1));

        $this->crearProducto([$archivo])->assertSessionHasNoErrors();

        $guardados = $this->archivosGuardados();

        $this->assertCount(1, $guardados);
        $this->assertMatchesRegularExpression('/^\d+_0_[a-z0-9]{12}\.png$/', $guardados[0]);
        $this->assertStringNotContainsString('foto', $guardados[0]);
    }

    #[DataProvider('nombresDeScript')]
    public function test_un_script_php_disfrazado_de_imagen_no_se_guarda(string $nombre): void
    {
        $archivo = $this->archivo($nombre, '<?php echo 1; ?>');

        $this->crearProducto([$archivo])->assertSessionHasErrors('images.0');

        $this->assertSame([], $this->archivosGuardados());
    }

    public static function nombresDeScript(): array
    {
        return [['shell.jpg'], ['shell.php.jpg'], ['shell.png'], ['shell.phtml'], ['shell.php'], ['.htaccess']];
    }

    public function test_un_gif_con_php_adentro_se_guarda_siempre_como_gif_nunca_como_php(): void
    {
        $poliglota = "GIF89a\x01\x00\x01\x00\x00\x00\x00;<?php echo 1; ?>";
        $archivo = $this->archivo('foto.jpg', $poliglota);

        $this->crearProducto([$archivo]);

        foreach ($this->archivosGuardados() as $nombre) {
            $this->assertMatchesRegularExpression('/\.(gif|png|jpg|webp)$/', $nombre);
            $this->assertDoesNotMatchRegularExpression('/\.(php|phtml|phar|pht|htaccess)/i', $nombre);
        }
    }

    public function test_la_carpeta_de_imagenes_queda_con_htaccess_que_impide_ejecutar_php(): void
    {
        $this->crearProducto([$this->archivo('a.png', base64_decode(self::PNG_1X1))]);

        $htaccess = $this->publicTemporal.'/images/products/.htaccess';

        $this->assertFileExists($htaccess);

        $contenido = file_get_contents($htaccess);

        $this->assertSame(CarpetaDeSubidas::HTACCESS."\n", $contenido);
        $this->assertStringContainsString('php_flag engine off', $contenido);
        $this->assertStringContainsString('Require all denied', $contenido);
        // Lista blanca de lo que SÍ se sirve: imágenes, videos y PDF.
        $this->assertMatchesRegularExpression('/jpe\?g\|png\|gif\|webp\|mp4/', $contenido);
        $this->assertStringNotContainsString('php|', str_replace('(?i)', '', preg_replace('/FilesMatch[^\n]*\n/', '', explode('Require all granted', $contenido)[0]) ?? ''));
    }

    public function test_el_htaccess_existente_no_se_pisa(): void
    {
        File::ensureDirectoryExists($this->publicTemporal.'/images/products');
        file_put_contents($this->publicTemporal.'/images/products/.htaccess', '# del hosting');

        CarpetaDeSubidas::proteger($this->publicTemporal.'/images/products');

        $this->assertSame('# del hosting', file_get_contents($this->publicTemporal.'/images/products/.htaccess'));
    }

    public function test_el_pdf_de_documentos_se_guarda_con_nombre_impredecible_y_carpeta_protegida(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.documents.store'), [
            'title' => 'Manual', 'type' => 'pdf', 'is_active' => true,
            'file' => UploadedFile::fake()->create('manual.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $archivos = array_values(array_diff(scandir($this->publicTemporal.'/docs/pdfs'), ['.', '..', '.htaccess']));

        $this->assertCount(1, $archivos);
        $this->assertMatchesRegularExpression('/^documento_\d+_[a-z0-9]{32}\.pdf$/', $archivos[0]);
        $this->assertFileExists($this->publicTemporal.'/docs/pdfs/.htaccess');
    }

    public function test_un_documento_que_no_es_pdf_se_rechaza(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.documents.store'), [
            'title' => 'Malo', 'type' => 'pdf',
            'file' => $this->archivo('malo.pdf', '<?php echo 1;'),
        ])->assertSessionHasErrors('file');

        $this->assertDirectoryDoesNotExist($this->publicTemporal.'/docs/pdfs');
    }

    public function test_el_tamano_maximo_se_respeta(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.documents.store'), [
            'title' => 'Enorme', 'type' => 'pdf',
            'file' => UploadedFile::fake()->create('enorme.pdf', 25000, 'application/pdf'),
        ])->assertSessionHasErrors('file');
    }

    public function test_el_vendedor_no_puede_subir_archivos_al_catalogo(): void
    {
        $vendedor = User::factory()->vendedor()->create();

        $this->actingAs($vendedor)->post(route('admin.products.store'), [
            'title' => 'X', 'description' => 'x', 'price' => 1, 'category_id' => Category::factory()->create()->id,
            'images' => [$this->archivo('a.png', base64_decode(self::PNG_1X1))],
        ])->assertForbidden();

        $this->assertSame([], $this->archivosGuardados());
    }
}
