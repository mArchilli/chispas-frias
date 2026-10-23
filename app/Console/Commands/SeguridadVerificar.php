<?php

namespace App\Console\Commands;

use App\Rukoz\SeguridadDelCentral;
use App\Support\CarpetaDeSubidas;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Chequeo de seguridad del entorno (S.2 · A.10, A.11, B.3). Se corre en el servidor el día del deploy y después de cada cambio de
 * hosting: `php artisan chispasfrias:seguridad-verificar --url=https://<el dominio de la web>`. Sale con código 1 si algo está mal.
 *
 *  1. Configuración: APP_ENV, APP_DEBUG, APP_KEY, APP_URL https, cookie segura, mail real, credenciales y TLS del Central.
 *  2. Archivos: nada sensible dentro de lo que sirve el web (public/ y las carpetas de subidas), nada ejecutable en las subidas, y las
 *     carpetas de subidas con su .htaccess (`--reparar` lo escribe si falta).
 *  3. Desde afuera (con --url): que `/.env`, `/.git/config`, los logs, `vendor/`, `composer.json`, los respaldos, etc. den 403/404, que
 *     el .htaccess de las subidas no se vea, y que un PHP puesto en la carpeta de imágenes NO se ejecute (se crea y se borra solo).
 */
class SeguridadVerificar extends Command
{
    protected $signature = 'chispasfrias:seguridad-verificar
        {--url= : URL pública del sitio (https://…, el dominio de la web) para probar desde afuera}
        {--reparar : escribe el .htaccess de las carpetas de subidas si falta}';

    protected $description = 'Revisa la configuración y los archivos expuestos (APP_DEBUG, .env, subidas, .htaccess, TLS del Central) y, con --url, lo prueba desde afuera';

    private int $fallas = 0;

    /** Rutas que NUNCA deben servirse: 403 o 404 son correctos; 200 (o un 301 a algo) no. */
    private const RUTAS_PROHIBIDAS = [
        '/.env', '/.env.production', '/.env.backup', '/.git/config', '/.git/HEAD', '/.htaccess', '/.htpasswd',
        '/storage/logs/laravel.log', '/storage/logs/seguridad.log', '/storage/framework/sessions', '/storage/app/private',
        '/vendor/autoload.php', '/vendor/composer/installed.json', '/composer.json', '/composer.lock', '/package.json', '/package-lock.json',
        '/artisan', '/phpunit.xml', '/vite.config.js', '/README.md', '/contextoproyecto.md',
        '/database/database.sqlite', '/backup.sql', '/dump.sql', '/db.sql', '/backup.zip', '/public_html.zip', '/site.tar.gz',
        '/config/app.php', '/app/Models/User.php', '/bootstrap/app.php', '/routes/web.php',
        '/images/products/.htaccess', '/docs/pdfs/.htaccess',
    ];

    public function handle(): int
    {
        $this->components->info('Configuración');
        $this->configuracion();

        $this->newLine();
        $this->components->info('Archivos del servidor');
        $this->archivos();

        if ($url = rtrim((string) $this->option('url'), '/')) {
            $this->newLine();
            $this->components->info("Desde afuera ({$url})");
            $this->desdeAfuera($url);
        } else {
            $this->newLine();
            $this->components->warn('Sin --url no se prueba desde afuera (/.env, /.git, logs, ejecución de PHP en las subidas). Corrélo con --url=https://...');
        }

        $this->newLine();
        $this->fallas === 0
            ? $this->components->info('Todo en orden.')
            : $this->components->error("{$this->fallas} problema(s). Resolvelos antes de seguir (docs/SEGURIDAD.md → Día del deploy).");

        return $this->fallas === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function ok(string $que, string $detalle = ''): void
    {
        $this->components->twoColumnDetail($que, '<fg=green>OK</>'.($detalle !== '' ? " {$detalle}" : ''));
    }

    private function mal(string $que, string $detalle): void
    {
        $this->fallas++;
        $this->components->twoColumnDetail($que, "<fg=red>MAL</> {$detalle}");
    }

    private function aviso(string $que, string $detalle): void
    {
        $this->components->twoColumnDetail($que, "<fg=yellow>AVISO</> {$detalle}");
    }

    private function configuracion(): void
    {
        $produccion = app()->isProduction();

        $produccion ? $this->ok('APP_ENV', 'production') : $this->mal('APP_ENV', 'es «'.app()->environment().'»: en el hosting tiene que ser production');
        config('app.debug') ? $this->mal('APP_DEBUG', 'está en true: muestra stack traces, variables y credenciales ante un error') : $this->ok('APP_DEBUG', 'false');
        config('app.key') ? $this->ok('APP_KEY', 'definida') : $this->mal('APP_KEY', 'vacía');
        str_starts_with((string) config('app.url'), 'https://') ? $this->ok('APP_URL', config('app.url')) : $this->mal('APP_URL', 'no es https: '.config('app.url'));

        if (config('session.secure') === true || ! $produccion) {
            $this->ok('SESSION_SECURE_COOKIE', config('session.secure') === true ? 'true' : 'n/a fuera de producción');
        } else {
            $this->mal('SESSION_SECURE_COOKIE', 'en producción tiene que ser true (la cookie de sesión solo viaja por HTTPS)');
        }

        config('session.http_only') ? $this->ok('SESSION_HTTP_ONLY', 'true') : $this->mal('SESSION_HTTP_ONLY', 'false');
        config('session.encrypt') ? $this->ok('SESSION_ENCRYPT', 'true') : $this->aviso('SESSION_ENCRYPT', 'false: las sesiones (base) guardan el carrito y el id de usuario sin cifrar. Recomendado true.');

        if ($produccion && in_array(config('mail.default'), ['log', 'array'], true)) {
            $this->mal('MAIL_MAILER', '«'.config('mail.default').'»: los mails de recuperar contraseña no se entregan (y quedan en el log)');
        } else {
            $this->ok('MAIL_MAILER', (string) config('mail.default'));
        }

        in_array(config('seguridad.csp.modo'), ['report-only', 'enforce'], true)
            ? $this->ok('CSP', (string) config('seguridad.csp.modo'))
            : $this->mal('CSP', 'apagada (SEGURIDAD_CSP=off)');

        if (config('rukoz.habilitada')) {
            foreach (['rukoz.url' => 'RUKOZ_CENTRAL_URL', 'rukoz.api_key' => 'RUKOZ_API_KEY', 'rukoz.secret' => 'RUKOZ_SECRET'] as $clave => $variable) {
                config($clave) ? $this->ok($variable, 'definida') : $this->mal($variable, 'falta');
            }

            ($advertencia = SeguridadDelCentral::advertenciaTls()) ? $this->mal('TLS del Central', $advertencia) : $this->ok('TLS del Central', 'https');

            config('rukoz.secret_anterior')
                ? $this->aviso('RUKOZ_SECRET_ANTERIOR', 'hay un secreto anterior cargado: si la rotación terminó (pasaron 24 h), borralo')
                : $this->ok('RUKOZ_SECRET_ANTERIOR', 'vacío (sin rotación en curso)');
        } else {
            $this->ok('Integración con el Central', 'apagada');
        }
    }

    private function archivos(): void
    {
        $carpetas = [
            'imágenes' => public_path(ltrim((string) config('tienda.imagenes.ruta'), '/')),
            'PDFs' => public_path(ltrim((string) config('documents.pdf_path'), '/')),
        ];

        foreach ($carpetas as $nombre => $dir) {
            $dir = rtrim($dir, '/\\');

            if (! is_dir($dir)) {
                $this->aviso("Carpeta de {$nombre}", "no existe todavía ({$dir}); se crea con la primera subida");

                continue;
            }

            if (! is_file($dir.DIRECTORY_SEPARATOR.'.htaccess')) {
                if ($this->option('reparar')) {
                    CarpetaDeSubidas::proteger($dir);
                    $this->ok("Carpeta de {$nombre}", '.htaccess escrito (--reparar)');
                } else {
                    $this->mal("Carpeta de {$nombre}", "sin .htaccess en {$dir} (corré con --reparar)");
                }
            } else {
                $this->ok("Carpeta de {$nombre}", '.htaccess presente');
            }

            $peligrosos = $this->archivosPeligrosos($dir, '/\.(php\d?|phtml|phar|pht|phps|cgi|pl|py|sh|asp|aspx|jsp|exe)$/i');
            $peligrosos === [] ? $this->ok("Ejecutables en {$nombre}", 'ninguno') : $this->mal("Ejecutables en {$nombre}", 'borrá: '.implode(', ', array_slice($peligrosos, 0, 5)));
        }

        $sensibles = $this->archivosPeligrosos(public_path(), '/(^\.env$|^\.env\.|\.sql$|\.sqlite3?$|\.zip$|\.tar$|\.gz$|\.bak$|\.backup$|\.old$|\.log$|^composer\.(json|lock)$|id_rsa)/i', excluir: ['build', 'node_modules']);
        $sensibles === [] ? $this->ok('Sensibles bajo public/', 'ninguno') : $this->mal('Sensibles bajo public/', implode(', ', array_slice($sensibles, 0, 8)));

        $pruebas = glob(public_path('docs').DIRECTORY_SEPARATOR.'test-*') ?: [];
        $pruebas === [] ? $this->ok('Carpetas de prueba (public/docs/test-*)', 'ninguna') : $this->mal('Carpetas de prueba', implode(', ', array_map('basename', $pruebas)));
    }

    /** @return list<string> rutas relativas a $base de los archivos cuyo NOMBRE coincide con el patrón. */
    private function archivosPeligrosos(string $base, string $patron, array $excluir = []): array
    {
        if (! is_dir($base)) {
            return [];
        }

        $encontrados = [];
        $iterador = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
                fn (\SplFileInfo $f) => ! ($f->isDir() && in_array($f->getFilename(), $excluir, true)),
            ),
        );

        foreach ($iterador as $archivo) {
            if ($archivo->isFile() && preg_match($patron, $archivo->getFilename()) === 1) {
                $encontrados[] = ltrim(str_replace([rtrim($base, '/\\'), '\\'], ['', '/'], $archivo->getPathname()), '/');
            }
        }

        return $encontrados;
    }

    private function desdeAfuera(string $url): void
    {
        foreach (self::RUTAS_PROHIBIDAS as $ruta) {
            try {
                $respuesta = Http::withoutRedirecting()->timeout(10)->withHeaders(['User-Agent' => 'chispasfrias-seguridad-verificar'])->get($url.$ruta);
            } catch (Throwable $e) {
                $this->aviso($ruta, 'no se pudo consultar ('.$e::class.')');

                continue;
            }

            // 403/404 son los esperados. Un 200 con contenido es el problema. (La app devuelve 404 para lo que no conoce.)
            in_array($respuesta->status(), [403, 404, 410], true)
                ? $this->ok($ruta, (string) $respuesta->status())
                : $this->mal($ruta, 'responde '.$respuesta->status().' (tiene que ser 403 o 404)');
        }

        $this->probarEjecucionDePhp($url);
    }

    /**
     * Deja un .php inofensivo en la carpeta de imágenes, lo pide por HTTP y lo borra. Si la respuesta trae la marca que imprime el
     * script, el servidor EJECUTA PHP en las subidas: cualquier archivo que se cuele ahí sería código.
     */
    private function probarEjecucionDePhp(string $url): void
    {
        $dir = rtrim(public_path(ltrim((string) config('tienda.imagenes.ruta'), '/')), '/\\');

        if (! is_dir($dir) || ! is_writable($dir)) {
            $this->aviso('Ejecución de PHP en las subidas', 'la carpeta de imágenes no existe o no se puede escribir; no se probó');

            return;
        }

        $nombre = 'prueba-seguridad-'.bin2hex(random_bytes(6)).'.php';
        $marca = 'EJECUTADO-'.bin2hex(random_bytes(6));
        $archivo = $dir.DIRECTORY_SEPARATOR.$nombre;
        $publica = '/'.trim((string) config('tienda.imagenes.url'), '/').'/'.$nombre;

        file_put_contents($archivo, "<?php echo '{$marca}';");

        try {
            $respuesta = Http::withoutRedirecting()->timeout(10)->get($url.$publica);

            str_contains($respuesta->body(), $marca)
                ? $this->mal('Ejecución de PHP en las subidas', 'el servidor EJECUTA PHP en la carpeta de imágenes. Revisá el .htaccess de esa carpeta.')
                : $this->ok('Ejecución de PHP en las subidas', 'no se ejecuta ('.$respuesta->status().')');
        } catch (Throwable $e) {
            $this->aviso('Ejecución de PHP en las subidas', 'no se pudo consultar ('.$e::class.')');
        } finally {
            @unlink($archivo);
        }
    }
}
