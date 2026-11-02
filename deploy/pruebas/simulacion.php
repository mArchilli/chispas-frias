<?php
/**
 * Simulador del hosting de DonWeb/Ferozo para probar deploy/runner.php en local (Windows + XAMPP), sin tocar ningún servidor.
 *
 *   php deploy/pruebas/simulacion.php preparar  --zip=C:\cf-test\cache\<commit12>\paquete.zip
 *   php deploy/pruebas/simulacion.php escenario <nombre> --zip=...
 *   php deploy/pruebas/simulacion.php todo --zip=...
 *
 * Arma en --base (por defecto C:\cf-test\sim) una cuenta «/home/c2731764» de mentira:
 *   home/laravel      el sitio ACTUAL = código del tag produccion-pre-integracion + el vendor del paquete, con su .env, storage y public/
 *   home/public_html  index.php propio del hosting, .htaccess viejo, robots.txt estático, un «hot» sospechoso, build, imágenes, docs, cgi-bin
 *   home/deploy-cf    runner.php (y lo que sube publicar.ps1)
 * y una base MariaDB propia (puerto 3399, base cf_deploy_test) con el esquema viejo, datos y vigencias de ofertas/cupones.
 * El PHP corre con un php.ini de prueba: disable_functions como Ferozo (exec, shell_exec, system, passthru, popen, proc_*, escapeshell*,
 * dl, pcntl_*, putenv, opcache_*), open_basedir = la cuenta + el temporal, max_execution_time=60 y memory_limit=512M.
 * (`eval` es una construcción del lenguaje y disable_functions no puede bloquearla: ver el escaneo de tokens en el informe.)
 *
 * Modo web (--web=1): en vez de correr deploy-cf/runner.php por la CLI, cada «corrida del cron» es un pedido simulado al punto de
 * entrada de public_html (home/_web.php fija REQUEST_METHOD/GET y carga cf-deploy-<hex>.php), como lo hace cronwget en Ferozo. Un
 * shim (home/_shim.php, auto_prepend_file) define opcache_invalidate() y anota cada llamada (la CLI de XAMPP no tiene opcache). Cuando
 * una corrida deja un punto de control (en_pausa), el simulador hace de «pedido encadenado» y vuelve a llamar.
 *
 * Los escenarios están al final del archivo.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$opts = [];
$args = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z-]+)=(.*)$/', $a, $m)) {
        $opts[$m[1]] = $m[2];
    } else {
        $args[] = $a;
    }
}

final class Sim
{
    public string $php = 'C:/xampp/php/php.exe';
    public string $mysql = 'C:/xampp/mysql/bin/mysql.exe';
    public string $mysqldump = 'C:/xampp/mysql/bin/mysqldump.exe';
    public string $repo;
    public string $base;
    public string $zip;
    public string $pristine;
    public string $sim;
    public string $home;
    public string $ini;
    public int $port = 8099;
    public int $dbPort = 3399;
    public string $db = 'cf_deploy_test';
    /** @var resource|null */
    private $server = null;
    public array $resultados = [];
    public bool $web = false;
    public string $token = 'simtoken-0123456789abcdef-sim';
    public string $stub = 'cf-deploy-0123456789abcdef.php';
    public int $vueltas = 0;

    public function __construct(array $o)
    {
        $this->repo = str_replace('\\', '/', realpath(__DIR__.'/../..'));
        $this->base = str_replace('\\', '/', $o['base'] ?? 'C:/cf-test/sim');
        $this->zip = str_replace('\\', '/', $o['zip'] ?? '');
        $this->pristine = $this->base.'/pristine';
        $this->sim = $this->base.'/sim';
        $this->home = $this->sim.'/home';
        $this->ini = $this->base.'/sim.ini';
        $this->web = ($o['web'] ?? '') === '1';
    }

    // --- utilidades ------------------------------------------------------------------------------------------------

    public function titulo(string $t): void
    {
        echo "\n\033[1;36m=== {$t} ===\033[0m\n";
    }

    public function check(string $nombre, bool $ok, string $detalle = ''): void
    {
        $this->resultados[] = [$nombre, $ok, $detalle];
        echo ($ok ? "  \033[32mPASS\033[0m " : "  \033[31mFAIL\033[0m ").$nombre.($detalle !== '' && ! $ok ? "  — {$detalle}" : ($detalle !== '' ? "  ({$detalle})" : ''))."\n";
    }

    /** @return array{0: int, 1: string} */
    public function run(array $cmd, ?string $cwd = null, array $env = [], ?string $stdin = null): array
    {
        $e = array_merge(getenv(), $env);
        $p = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd, $e);
        if (! is_resource($p)) {
            throw new RuntimeException('proc_open falló: '.implode(' ', $cmd));
        }
        if ($stdin !== null) {
            fwrite($pipes[0], $stdin);
        }
        fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($p), $out.$err];
    }

    public function rmdirWin(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        // robocopy /MIR contra una carpeta vacía borra también rutas largas y reintenta archivos trabados
        $vacia = $this->base.'/_vacia';
        @mkdir($vacia, 0777, true);
        $w = static fn (string $p): string => str_replace('/', '\\', $p);
        for ($i = 0; $i < 3 && (clearstatcache() || is_dir($dir)); $i++) {
            $this->run(['robocopy', $w($vacia), $w($dir), '/MIR', '/NFL', '/NDL', '/NJH', '/NJS', '/NP', '/MT:32', '/R:1', '/W:1']);
            $this->run(['cmd', '/c', 'rmdir', '/s', '/q', $w($dir)]);
            if (is_dir($dir)) {
                usleep(700000);
            }
        }
        clearstatcache();
        if (is_dir($dir)) {
            throw new RuntimeException("No pude borrar {$dir}");
        }
    }

    public function copiar(string $de, string $a): void
    {
        [$c] = $this->run(['robocopy', str_replace('/', '\\', $de), str_replace('/', '\\', $a), '/E', '/NFL', '/NDL', '/NJH', '/NJS', '/NP', '/MT:32', '/R:1', '/W:1']);
        if ($c >= 8) {
            throw new RuntimeException("robocopy {$de} → {$a} falló ({$c})");
        }
    }

    public function mkdir(string $d): void
    {
        if (! is_dir($d)) {
            mkdir($d, 0777, true);
        }
    }

    public function pdo(?string $db = null): PDO
    {
        return new PDO('mysql:host=127.0.0.1;port='.$this->dbPort.($db === null ? ';dbname='.$this->db : ''), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    // --- php.ini de prueba ----------------------------------------------------------------------------------------

    public function escribirIni(): void
    {
        $base = file('C:/xampp/php/php.ini', FILE_IGNORE_NEW_LINES);
        $ext = array_values(array_filter($base, static fn (string $l): bool => (bool) preg_match('/^\s*(extension|zend_extension)\s*=/', $l)));
        $tmp = str_replace('\\', '/', sys_get_temp_dir());
        $dis = 'exec,shell_exec,system,passthru,popen,proc_open,proc_close,proc_get_status,proc_terminate,proc_nice,escapeshellarg,escapeshellcmd,dl,putenv,'
            .'pcntl_alarm,pcntl_fork,pcntl_waitpid,pcntl_wait,pcntl_wifexited,pcntl_wifstopped,pcntl_wifsignaled,pcntl_wifcontinued,pcntl_wexitstatus,pcntl_wtermsig,pcntl_wstopsig,'
            .'pcntl_signal,pcntl_signal_get_handler,pcntl_signal_dispatch,pcntl_get_last_error,pcntl_strerror,pcntl_sigprocmask,pcntl_sigwaitinfo,pcntl_sigtimedwait,pcntl_exec,'
            .'pcntl_getpriority,pcntl_setpriority,pcntl_async_signals,opcache_reset,opcache_get_status';
        $ini = "; php.ini de prueba (simula Ferozo)\nextension_dir=\"C:/xampp/php/ext\"\n".implode("\n", $ext)."\n"
            ."date.timezone=UTC\nmemory_limit=512M\nmax_execution_time=60\ndisplay_errors=1\nerror_reporting=E_ALL\nlog_errors=0\nallow_url_fopen=1\n"
            ."disable_functions={$dis}\nopen_basedir=\"{$this->home};{$tmp}\"\nsys_temp_dir=\"{$tmp}\"\nauto_prepend_file=\"{$this->home}/_shim.php\"\n";
        $this->mkdir($this->base);
        file_put_contents($this->ini, $ini);
    }

    // --- servidor web (php -S, con el index.php propio del hosting) ------------------------------------------------

    public function iniciarServidor(): void
    {
        $this->detenerServidor();
        $router = $this->home.'/_router.php'; // adentro de la cuenta: open_basedir no deja leerlo desde afuera
        file_put_contents($router, <<<'PHP'
<?php
// Emula lo que hace Apache con el .htaccess de Laravel: los archivos que existen se sirven tal cual, el resto va a index.php.
$uri = urldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$doc = $_SERVER['DOCUMENT_ROOT'];
// Prueba del .htaccess que el servidor rechaza (500) cuando se pide con SIM_HTACCESS_500=1 y tiene los bloques S.2.
$ht = $doc.'/.htaccess';
if (getenv('SIM_HTACCESS_500') === '1' && is_file($ht) && str_contains((string) file_get_contents($ht), '# --- Archivos que nunca se sirven')) {
    http_response_code(500);
    echo 'Internal Server Error (simulado: directiva no admitida en .htaccess)';
    return true;
}
if (preg_match('#(^|/)\.#', $uri) && ! str_starts_with($uri, '/.well-known/')) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}
if (preg_match('#^/cf-deploy-[0-9a-f]{16}\.php$#', $uri) && is_file($doc.$uri)) {
    return false; // el punto de entrada del deploy es un .php real de public_html
}
if ($uri !== '/' && is_file($doc.$uri) && ! preg_match('/\.php$/i', $uri)) {
    return false;
}
require $doc.'/index.php';
PHP);
        $log = $this->base.'/server.log';
        $env = array_merge(getenv(), ['SIM_HTACCESS_500' => getenv('SIM_HTACCESS_500') ?: '0']);
        $this->server = proc_open([$this->php, '-c', $this->ini, '-S', '127.0.0.1:'.$this->port, '-t', $this->home.'/public_html', $router], [0 => ['pipe', 'r'], 1 => ['file', $log, 'w'], 2 => ['file', $log, 'a']], $pipes, $this->home, $env);
        for ($i = 0; $i < 50; $i++) {
            $f = @fsockopen('127.0.0.1', $this->port, $en, $es, 0.2);
            if ($f) {
                fclose($f);

                return;
            }
            usleep(200000);
        }
        throw new RuntimeException('El servidor de prueba no arrancó.');
    }

    public function detenerServidor(): void
    {
        if (is_resource($this->server)) {
            $st = proc_get_status($this->server);
            if ($st['running']) {
                $this->run(['taskkill', '/F', '/T', '/PID', (string) $st['pid']]);
            }
            proc_close($this->server);
            $this->server = null;
            usleep(400000);
        }
    }

    public function http(string $ruta, array $o = []): array
    {
        $ch = curl_init('http://127.0.0.1:'.$this->port.$ruta);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_FOLLOWLOCATION => false]);
        if (isset($o['post'])) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $o['post']);
        }
        $b = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['code' => $code, 'body' => (string) $b];
    }

    // --- el sitio «de hoy» ------------------------------------------------------------------------------------------

    public function preparar(): void
    {
        $this->titulo('Preparar el sitio simulado de hoy (tag produccion-pre-integracion + vendor del paquete)');
        if ($this->zip === '' || ! is_file($this->zip)) {
            throw new RuntimeException('Falta --zip=<paquete.zip> (lo arma publicar.ps1 -SoloLocal).');
        }
        $this->rmdirWin($this->pristine);
        $h = $this->pristine.'/home';
        $this->mkdir($h.'/laravel');
        $this->mkdir($h.'/deploy-cf');
        $this->escribirIni();

        // 1) código viejo
        $tmpZip = $this->base.'/tag.zip';
        [$c, $o] = $this->run(['git', '-C', $this->repo, 'archive', '--format=zip', '-o', $tmpZip, 'produccion-pre-integracion']);
        if ($c !== 0) {
            throw new RuntimeException('git archive falló: '.$o);
        }
        $z = new ZipArchive();
        $z->open($tmpZip);
        $z->extractTo($h.'/laravel');
        $z->close();
        unlink($tmpZip);
        $this->rmdirWin($h.'/laravel/public');
        foreach (['tests', 'docs', 'node_modules'] as $d) {
            $this->rmdirWin($h.'/laravel/'.$d);
        }
        // 2) vendor del paquete (non-dev)
        $z = new ZipArchive();
        $z->open($this->zip, ZipArchive::RDONLY);
        $nombres = [];
        for ($i = 0; $i < $z->numFiles; $i++) {
            $n = (string) $z->getNameIndex($i);
            if (str_starts_with($n, 'laravel/vendor/')) {
                $nombres[] = $n;
            }
        }
        $tmpV = $this->base.'/vendor-extraido';
        $this->rmdirWin($tmpV);
        $z->extractTo($tmpV, $nombres);
        $z->close();
        $this->copiar($tmpV.'/laravel/vendor', $h.'/laravel/vendor');
        $this->rmdirWin($tmpV);
        // 3) estructura de laravel/
        foreach (['storage/app/public', 'storage/app/private', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache', 'public/docs/pdfs'] as $d) {
            $this->mkdir($h.'/laravel/'.$d);
        }
        file_put_contents($h.'/laravel/storage/logs/laravel.log', "[2026-10-30 10:00:00] production.ERROR: error viejo que no cuenta\n");
        file_put_contents($h.'/laravel/public/docs/pdfs/subida-dentro-de-laravel-public.pdf', '%PDF-1.4 prueba');
        $key = 'base64:'.base64_encode(random_bytes(32));
        $env = <<<ENV
APP_NAME="Chispa Fria"
APP_ENV=production
APP_KEY={$key}
APP_DEBUG=false
APP_URL=http://127.0.0.1:{$this->port}
APP_LOCALE=es
APP_MAINTENANCE_DRIVER=file
LOG_CHANNEL=single
LOG_LEVEL=warning
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT={$this->dbPort}
DB_DATABASE={$this->db}
DB_USERNAME=root
DB_PASSWORD=
SESSION_DRIVER=database
SESSION_LIFETIME=120
CACHE_STORE=database
QUEUE_CONNECTION=database
PRODUCT_IMAGES_PATH="/../../public_html/images/products/"
VITE_PRODUCT_IMAGES_PATH="/images/products/"
DOCUMENTS_PDF_PATH="/../../public_html/docs/pdfs/"
MAIL_MAILER=log
RUKOZ_INTEGRACION=true
INTEGRACION_STOCK=true
INTEGRACION_CHECKOUT=true
OTRA_VARIABLE_QUE_NO_SE_TOCA="con espacios y # numeral"

ENV;
        file_put_contents($h.'/laravel/.env', $env);
        // 4) public_html
        $p = $h.'/public_html';
        foreach (['images/products', 'docs/pdfs', 'videos', 'cgi-bin', 'tmp'] as $d) {
            $this->mkdir($p.'/'.$d);
        }
        file_put_contents($p.'/index.php', <<<'PHP'
<?php
// index.php propio del hosting: apunta a ../laravel
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__.'/../laravel/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../laravel/vendor/autoload.php';

$app = require_once __DIR__.'/../laravel/bootstrap/app.php';

$app->handleRequest(Request::capture());
PHP);
        $tagHt = $this->run(['git', '-C', $this->repo, 'show', 'produccion-pre-integracion:public/.htaccess'])[1];
        file_put_contents($p.'/.htaccess', "# htaccess del hosting (sim)\n".$tagHt);
        file_put_contents($p.'/robots.txt', "User-agent: *\nDisallow:\n");
        file_put_contents($p.'/hot', "http://[::1]:5173\n");
        copy($this->repo.'/public/favicon.ico', $p.'/favicon.ico');
        copy($this->repo.'/public/chispas-frias-logo.png', $p.'/chispas-frias-logo.png');
        $this->copiar($this->repo.'/public/build', $p.'/build');
        $this->copiar($this->repo.'/public/build', $h.'/laravel/public/build');
        copy($this->repo.'/public/images/chispas-frias-logo.png', $p.'/images/chispas-frias-logo.png');
        copy($this->repo.'/public/images/carrusel-1.jpg', $p.'/images/products/1700000000_0_aaaaaaaa.jpg');
        copy($this->repo.'/public/images/carrusel-2.jpg', $p.'/images/products/1700000000_1_bbbbbbbb.jpg');
        file_put_contents($p.'/images/products/.htaccess', "# subidas\n");
        file_put_contents($p.'/docs/pdfs/manual.pdf', '%PDF-1.4 manual');
        file_put_contents($p.'/videos/demo.mp4', random_bytes(2048));
        file_put_contents($p.'/cgi-bin/.keep', '');

        // 5) base: esquema viejo con el código viejo (artisan del tag con el vendor del paquete)
        $pdo = $this->pdo(null);
        $pdo->exec("DROP DATABASE IF EXISTS {$this->db}");
        $pdo->exec("CREATE DATABASE {$this->db} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        [$c, $o] = $this->run([$this->php, 'artisan', 'migrate', '--force'], $h.'/laravel');
        if ($c !== 0) {
            throw new RuntimeException("migrate del sitio viejo falló:\n".$o);
        }
        // El sitio de hoy no tiene el paquete rukoz: sus migraciones (que el vendor de este paquete sí autodescubre) se sacan de la base.
        $pdoR = $this->pdo();
        foreach ($pdoR->query("SHOW TABLES LIKE 'rukoz\_%'")->fetchAll(PDO::FETCH_COLUMN) as $t) {
            $pdoR->exec("DROP TABLE `{$t}`");
        }
        $pdoR->exec("DELETE FROM migrations WHERE migration LIKE '%rukoz%'");
        foreach (['CategoriesSeeder', 'ProductsSeeder'] as $seeder) { // DatabaseSeeder usa factories (faker es dependencia de desarrollo)
            [$c, $o] = $this->run([$this->php, 'artisan', 'db:seed', '--class='.$seeder, '--force'], $h.'/laravel');
            echo "  db:seed {$seeder} → código {$c}\n";
            if ($c !== 0) {
                echo substr($o, 0, 600)."\n";
                break;
            }
        }
        $this->datosDePrueba();
        @unlink($h.'/laravel/storage/logs/laravel.log');
        file_put_contents($h.'/laravel/storage/logs/laravel.log', "[2026-10-30 10:00:00] production.ERROR: error viejo que no cuenta\n");
        $this->rmdirWin($h.'/laravel/bootstrap/cache');
        $this->mkdir($h.'/laravel/bootstrap/cache');
        file_put_contents($h.'/laravel/bootstrap/cache/.gitignore', "*\n!.gitignore\n");

        // dump pristine de la base
        $d = $this->pristine.'/db.sql';
        [$c, $o] = $this->run([$this->mysqldump, '-h127.0.0.1', '-P'.$this->dbPort, '-uroot', '--single-transaction', '--routines', '--triggers', '--result-file='.$d, $this->db]);
        if ($c !== 0) {
            throw new RuntimeException('mysqldump falló: '.$o);
        }
        echo '  sitio base listo en '.$h.' · dump de la base '.round(filesize($d) / 1024)." KB\n";
    }

    private function datosDePrueba(): void
    {
        $pdo = $this->pdo();
        $ahora = '2026-10-01 12:00:00';
        $cols = static fn (PDO $p, string $t): array => array_column($p->query("SHOW COLUMNS FROM `{$t}`")->fetchAll(PDO::FETCH_ASSOC), null, 'Field');
        // Completa con 0 / '' / fecha las columnas NOT NULL sin default que el dato de prueba no trae.
        $rellenar = static function (array $f, array $columnas) use ($ahora): array {
            foreach ($columnas as $n => $c) {
                if (! array_key_exists($n, $f) && $c['Null'] === 'NO' && $c['Default'] === null && stripos((string) $c['Extra'], 'auto_increment') === false) {
                    $f[$n] = preg_match('/int|decimal|float|double|bool/i', $c['Type']) ? 0 : (preg_match('/date|time/i', $c['Type']) ? $ahora : 'x');
                }
            }

            return $f;
        };
        $existe = static fn (PDO $p, string $t): bool => (bool) $p->query("SHOW TABLES LIKE '{$t}'")->fetchColumn();
        // usuario admin
        if ($existe($pdo, 'users') && (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0) {
            $u = $cols($pdo, 'users');
            $f = ['name' => 'Admin', 'email' => 'admin@example.com', 'password' => password_hash('x', PASSWORD_BCRYPT), 'created_at' => $ahora, 'updated_at' => $ahora, 'role' => 'admin', 'is_active' => 1];
            $f = array_intersect_key($f, $u);
            $pdo->prepare('INSERT INTO users ('.implode(',', array_keys($f)).') VALUES ('.implode(',', array_fill(0, count($f), '?')).')')->execute(array_values($f));
        }
        $productos = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
        echo "  productos sembrados: {$productos}\n";
        if ($productos === 0) {
            throw new RuntimeException('La base no tiene productos: la home y /productos no se pueden comparar.');
        }
        $pid = (int) $pdo->query('SELECT id FROM products ORDER BY id LIMIT 1')->fetchColumn();
        // vigencias: ofertas y cupones con fechas (UTC), una con NULL
        $o = $cols($pdo, 'product_offers');
        $base = ['product_id' => $pid, 'offer_price' => 1000, 'percentage_discount' => 10, 'start_date' => '2026-10-31 00:00:00', 'end_date' => '2026-11-30 23:59:00', 'is_active' => 1, 'created_at' => $ahora, 'updated_at' => $ahora];
        foreach ([$base, array_merge($base, ['start_date' => null, 'end_date' => '2026-12-31 23:59:59'])] as $f) {
            $f = $rellenar(array_intersect_key($f, $o), $o);
            $pdo->prepare('INSERT INTO product_offers ('.implode(',', array_keys($f)).') VALUES ('.implode(',', array_fill(0, count($f), '?')).')')->execute(array_values($f));
        }
        $d = $cols($pdo, 'discount_codes');
        $f = $rellenar(array_intersect_key(['code' => 'TEST10', 'type' => 'percentage', 'value' => 10, 'percentage' => 10, 'start_date' => '2026-10-15 03:00:00', 'end_date' => null, 'is_active' => 1, 'created_at' => $ahora, 'updated_at' => $ahora], $d), $d);
        $pdo->prepare('INSERT INTO discount_codes ('.implode(',', array_keys($f)).') VALUES ('.implode(',', array_fill(0, count($f), '?')).')')->execute(array_values($f));
        echo '  ofertas: '.$pdo->query('SELECT COUNT(*) FROM product_offers')->fetchColumn().' · cupones: '.$pdo->query('SELECT COUNT(*) FROM discount_codes')->fetchColumn()."\n";
        // una fila con caracteres raros, para probar el volcado/restauración
        if ($existe($pdo, 'settings')) {
            $s = $cols($pdo, 'settings');
            $f = array_intersect_key(['key' => 'prueba_volcado', 'value' => "línea 1\nlínea 2 con 'comillas', \"dobles\", \\ barra, ñ, ✓ y ; punto y coma\n-- @@ casi sentinela", 'created_at' => $ahora, 'updated_at' => $ahora], $s);
            $pdo->prepare('INSERT INTO settings ('.implode(',', array_map(static fn ($c) => "`{$c}`", array_keys($f))).') VALUES ('.implode(',', array_fill(0, count($f), '?')).')')->execute(array_values($f));
        }
    }

    public function reiniciar(): void
    {
        $this->detenerServidor();
        $this->rmdirWin($this->sim);
        $this->mkdir($this->sim);
        $this->copiar($this->pristine.'/home', $this->home);
        copy($this->repo.'/deploy/runner.php', $this->home.'/deploy-cf/runner.php');
        $this->instalarAuxiliares();
        $this->envSet(['SEGURIDAD_FORZAR_HTTPS' => 'false']); // el simulador sólo habla http local
        if (getenv('SIM_INDEX_REAL') === '1') {
            // El index.php REAL de Chispas Frías (relevado por FTP el 2026-11-01): busca maintenance.php en ../storage (no en ../laravel/storage).
            file_put_contents($this->home.'/public_html/index.php', <<<'PHP'
<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../laravel/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../laravel/bootstrap/app.php';

$app->handleRequest(Request::capture());
PHP);
        }
        $pdo = $this->pdo(null);
        $pdo->exec("DROP DATABASE IF EXISTS {$this->db}");
        $pdo->exec("CREATE DATABASE {$this->db} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        [$c, $o] = $this->run([$this->mysql, '-h127.0.0.1', '-P'.$this->dbPort, '-uroot', $this->db], null, [], (string) file_get_contents($this->pristine.'/db.sql'));
        if ($c !== 0) {
            throw new RuntimeException('Restaurar el dump pristine falló: '.$o);
        }
        $this->iniciarServidor();
    }

    // --- el runner --------------------------------------------------------------------------------------------------

    public function disparo(string $modo, array $extra = []): array
    {
        $dc = $this->home.'/deploy-cf';
        if (! is_file($dc.'/paquete.zip')) {
            copy($this->zip, $dc.'/paquete.zip');
        }
        $d = array_merge([
            'modo' => $modo, 'commit' => $this->commit(), 'zip' => 'paquete.zip', 'zip_sha256' => hash_file('sha256', $dc.'/paquete.zip'),
            'zip_bytes' => filesize($dc.'/paquete.zip'), 'nonce' => bin2hex(random_bytes(16)), 'creado' => gmdate('Y-m-d\TH:i:s\Z'), 'htaccess' => 'no', 'origen' => 'simulacion',
        ], $extra);
        file_put_contents($dc.'/disparo.json', json_encode($d));

        return $d;
    }

    /** Cambia (o agrega) variables del .env del sitio simulado de hoy. */
    public function envSet(array $kv): void
    {
        $p = $this->home.'/laravel/.env';
        $t = (string) file_get_contents($p);
        foreach ($kv as $k => $v) {
            if (preg_match('/^'.preg_quote((string) $k, '/').'=.*$/m', $t)) {
                $linea = $k.'='.$v;
                $t = (string) preg_replace_callback('/^'.preg_quote((string) $k, '/').'=.*$/m', static fn (array $m): string => $linea, $t);
            } else {
                $t = rtrim($t, "\n")."\n{$k}={$v}\n";
            }
        }
        file_put_contents($p, $t);
    }

    public function commit(): string
    {
        $z = new ZipArchive();
        $z->open($this->zip, ZipArchive::RDONLY);
        $m = json_decode((string) $z->getFromName('manifest.json'), true);
        $z->close();

        return (string) $m['commit'];
    }

    /** Shim de opcache, simulador de pedido web y el punto de entrada de public_html (con el token de prueba). */
    public function instalarAuxiliares(): void
    {
        $this->escribirIni();
        @unlink($this->opcacheLog());
        file_put_contents($this->home.'/_shim.php', <<<'SHIM'
<?php
// Simula lo que el hosting tiene y la CLI de XAMPP no: opcache_invalidate() (opcache.restrict_api vacío). Anota cada llamada.
if (! function_exists('opcache_invalidate')) {
    function opcache_invalidate(string $filename, bool $force = false): bool
    {
        @file_put_contents(sys_get_temp_dir().'/cf-opcache-sim.log', ($force ? 'F ' : 'f ').str_replace('\\', '/', $filename)."\n", FILE_APPEND);

        return true;
    }
}
SHIM);
        file_put_contents($this->home.'/_web.php', <<<'WEB'
<?php
// Un pedido web simulado al punto de entrada (lo que hace cronwget en Ferozo o el gatillo de publicar.ps1), corrido por la CLI.
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'Wget/1.19.5 (sim)';
$_SERVER['SCRIPT_NAME'] = '/'.getenv('SIM_STUB');
$_GET = ['t' => (string) getenv('SIM_TOKEN')];
if (getenv('SIM_QS')) {
    parse_str((string) getenv('SIM_QS'), $extra);
    $_GET += $extra;
}
require __DIR__.'/public_html/'.getenv('SIM_STUB');
WEB);
        $stub = str_replace('__TOKEN_SHA256__', hash('sha256', $this->token), (string) file_get_contents($this->repo.'/deploy/entrada.php'));
        file_put_contents($this->home.'/public_html/'.$this->stub, $stub);
    }

    public function opcacheLog(): string
    {
        return str_replace('\\', '/', sys_get_temp_dir()).'/cf-opcache-sim.log';
    }

    /**
     * Una «corrida del cron». Si el motor deja un punto de control (en_pausa), el simulador hace de pedido encadenado y vuelve a llamar,
     * hasta que termine (o muera: código 99). En modo web la llamada pasa por el punto de entrada; si no, es la CLI de siempre.
     *
     * @return array{0: int, 1: string, 2: array} código, salida, estado.json
     */
    public function correrRunner(string $log, array $env = []): array
    {
        // CF_RUNNER_PRUEBA=sim: el runner acepta APP_URL http (en producción exige https); falla:/muere:/segmento:/tope: inyectan pruebas.
        $salida = '';
        $this->vueltas = 0;
        do {
            $cmd = $this->web ? [$this->php, '-c', $this->ini, $this->home.'/_web.php'] : [$this->php, '-c', $this->ini, $this->home.'/deploy-cf/runner.php'];
            [$c, $o] = $this->run($cmd, $this->base, array_merge(['CF_RUNNER_PRUEBA' => 'sim', 'SIM_STUB' => $this->stub, 'SIM_TOKEN' => $this->token], $env));
            $salida .= $o;
            $e = json_decode((string) @file_get_contents($this->home.'/deploy-cf/estado.json'), true) ?: [];
            $sigue = $c === 0 && ($e['estado'] ?? '') === 'ejecutando' && ! empty($e['en_pausa']) && ! isset($env['SIM_UNA_VEZ']);
            $this->vueltas++;
        } while ($sigue && $this->vueltas < 60);
        file_put_contents($this->base.'/logs/'.$log.'.log', $salida);

        return [$c, $salida, $e];
    }

    // --- fotografías del sitio, para comparar antes/después de una vuelta atrás --------------------------------------

    public function arbol(string $dir, array $excluir = []): string
    {
        $dir = rtrim(str_replace('\\', '/', $dir), '/');
        $items = [];
        if (! is_dir($dir)) {
            return 'NO EXISTE';
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY);
        foreach ($it as $f) {
            if (! $f->isFile()) {
                continue;
            }
            $rel = substr(str_replace('\\', '/', $f->getPathname()), strlen($dir) + 1);
            foreach ($excluir as $e) {
                if ($rel === $e || str_starts_with($rel, $e.'/')) {
                    continue 2;
                }
            }
            $items[$rel] = sha1_file($f->getPathname());
        }
        ksort($items);

        return sha1(json_encode($items));
    }

    /** @return array<string, string> */
    public function foto(): array
    {
        $l = $this->home.'/laravel';
        $f = [
            'laravel' => $this->arbol($l, ['vendor', 'storage/logs', 'storage/framework', 'bootstrap/cache']),
            'vendor/installed.json' => is_file($l.'/vendor/composer/installed.json') ? sha1_file($l.'/vendor/composer/installed.json') : 'NO EXISTE',
            'laravel/public' => $this->arbol($l.'/public'),
            'public_html' => $this->arbol($this->home.'/public_html'),
            'env' => is_file($l.'/.env') ? sha1_file($l.'/.env') : 'NO EXISTE',
            'down' => is_file($l.'/storage/framework/down') ? 'ABAJO' : 'arriba',
        ];
        $pdo = $this->pdo();
        $sum = [];
        foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
            if (in_array($t, ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'], true)) {
                continue;
            }
            $r = $pdo->query("CHECKSUM TABLE `{$t}`")->fetch(PDO::FETCH_NUM);
            $sum[$t] = (string) $r[1];
        }
        ksort($sum);
        $f['base'] = sha1(json_encode($sum));
        $f['base_tablas'] = (string) count($sum);

        return $f;
    }

    /** @param array<string, string> $a @param array<string, string> $b */
    public function iguales(array $a, array $b): array
    {
        $dif = [];
        foreach ($a as $k => $v) {
            if (($b[$k] ?? null) !== $v) {
                $dif[] = $k;
            }
        }

        return $dif;
    }

    public function ultimoRunnerLog(): string
    {
        return (string) @file_get_contents($this->home.'/deploy-cf/runner.log');
    }

    public function backups(): array
    {
        return glob($this->home.'/backups-cf/*', GLOB_ONLYDIR) ?: [];
    }
}

// =====================================================================================================================
// Escenarios
// =====================================================================================================================

$sim = new Sim($opts);
@mkdir($sim->base.'/logs', 0777, true);
$punto = static fn (string $m, string $p): string => $m.':'.$p;

$escenarios = [];

/** Ensayo: no toca nada. */
$escenarios['ensayo'] = function (Sim $s): void {
    $s->titulo('ensayo — verificaciones sin tocar el sitio');
    $s->reiniciar();
    $antes = $s->foto();
    $s->disparo('ensayo');
    [$c, $o, $e] = $s->correrRunner('ensayo');
    $despues = $s->foto();
    $s->check('el runner termina bien (código 0, estado ok)', $c === 0 && ($e['estado'] ?? '') === 'ok', "código {$c}, estado ".($e['estado'] ?? '?').' '.($e['error'] ?? ''));
    $s->check('no se modificó nada (laravel, public_html, .env, base)', $s->iguales($antes, $despues) === [], implode(',', $s->iguales($antes, $despues)));
    $s->check('no se creó backups-cf ni el sitio quedó en mantenimiento', $s->backups() === [] && $despues['down'] === 'arriba');
    $s->check('el disparo se borró y el paquete sigue', ! is_file($s->home.'/deploy-cf/disparo.json') && is_file($s->home.'/deploy-cf/paquete.zip'));
    $s->check('registra ensayo_ok para este paquete', isset($e['ensayo_ok']['zip_sha256']));
    $s->check('la tienda sigue respondiendo 200', $s->http('/')['code'] === 200);
    $s->check('el log cuenta el relevamiento de la CLI', str_contains($s->ultimoRunnerLog(), 'disable_functions:') && str_contains($s->ultimoRunnerLog(), 'open_basedir:'));
    $s->check('el ensayo arrancó el Laravel nuevo en una carpeta temporal', str_contains($s->ultimoRunnerLog(), 'rutas registradas'));
    $s->check('el ensayo informa el mantenimiento estimado (con detalle) y entra en el tope', isset($e['ventana_estimada']['seg']) && $e['ventana_estimada']['seg'] > 0 && $e['ventana_estimada']['seg'] < 480 && str_contains((string) ($e['mensaje'] ?? ''), 'Mantenimiento estimado'), json_encode($e['ventana_estimada'] ?? null));
    $s->check($s->web ? 'prueba web: el pedido de la app a su propio punto de entrada dio 204' : 'prueba web: sin punto de entrada (CLI) se avisa y se sigue', $s->web ? str_contains($s->ultimoRunnerLog(), 'Pedido a sí mismo') : str_contains($s->ultimoRunnerLog(), 'No se probó el pedido a sí mismo'));
};

$escenarios['deploy'] = function (Sim $s, string $nombre = 'deploy', array $extraDisparo = [], array $envRunner = []): array {
    $s->titulo("{$nombre} — ensayo + deploy exitoso");
    $s->reiniciar();
    $antes = $s->foto();
    $s->disparo('ensayo');
    [$c0, , $e0] = $s->correrRunner($nombre.'-ensayo', $envRunner);
    $s->check('ensayo previo OK', $c0 === 0 && ($e0['estado'] ?? '') === 'ok', ($e0['error'] ?? ''));
    $pdoAntes = $s->pdo();
    $vigAntes = $pdoAntes->query('SELECT id, start_date, end_date FROM product_offers ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $cuponAntes = $pdoAntes->query('SELECT id, start_date, end_date FROM discount_codes ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $migAntes = (int) $pdoAntes->query('SELECT COUNT(*) FROM migrations')->fetchColumn();
    $s->disparo('deploy', $extraDisparo);
    [$c, $o, $e] = $s->correrRunner($nombre, $envRunner);
    $s->check('el deploy termina bien (código 0, estado ok)', $c === 0 && ($e['estado'] ?? '') === 'ok', "código {$c}, estado ".($e['estado'] ?? '?').' · '.($e['error'] ?? ''));
    $l = $s->home.'/laravel';
    $p = $s->home.'/public_html';
    $pdo = $s->pdo();
    $s->check('laravel/ es el código nuevo (.cf-version.json con el commit)', is_file($l.'/.cf-version.json') && (json_decode((string) file_get_contents($l.'/.cf-version.json'), true)['commit'] ?? '') === $s->commit());
    $env = (string) file_get_contents($l.'/.env');
    $s->check('.env: RUKOZ_INTEGRACION e INTEGRACION_* en false', preg_match('/^RUKOZ_INTEGRACION=false$/m', $env) === 1 && preg_match('/^INTEGRACION_STOCK=false$/m', $env) === 1 && preg_match('/^INTEGRACION_CHECKOUT=false$/m', $env) === 1 && preg_match('/^INTEGRACION_CATALOGO=false$/m', $env) === 1);
    $s->check('.env: el resto de las variables quedó igual (APP_KEY, DB_*, valor con espacios y #)', str_contains($env, 'OTRA_VARIABLE_QUE_NO_SE_TOCA="con espacios y # numeral"') && str_contains($env, 'DB_DATABASE=cf_deploy_test') && str_contains($env, 'APP_KEY=base64:'));
    $nuevas = (int) $pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn() - $migAntes;
    $s->check('migraciones aplicadas: 14 (7 de la web + 7 del paquete)', $nuevas === 14, "aplicadas {$nuevas}");
    $vigDespues = $pdo->query('SELECT id, start_date, end_date FROM product_offers ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $okVig = true;
    foreach ($vigAntes as $i => $f) {
        foreach (['start_date', 'end_date'] as $c2) {
            $esp = $f[$c2] === null ? null : gmdate('Y-m-d H:i:s', strtotime($f[$c2].' UTC') + 10800);
            $okVig = $okVig && ($vigDespues[$i][$c2] === $esp);
        }
    }
    $s->check('vigencias de ofertas corridas +3 h exactas (y los NULL intactos)', $okVig, json_encode([$vigAntes, $vigDespues]));
    $cupDespues = $pdo->query('SELECT id, start_date, end_date FROM discount_codes ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $s->check('vigencia del cupón +3 h', $cupDespues[0]['start_date'] === gmdate('Y-m-d H:i:s', strtotime($cuponAntes[0]['start_date'].' UTC') + 10800) && $cupDespues[0]['end_date'] === null);
    $s->check('public_html: robots.txt estático y hot borrados; index.php sin tocar', ! is_file($p.'/robots.txt') && ! is_file($p.'/hot') && str_contains((string) file_get_contents($p.'/index.php'), 'maintenance.php'));
    $s->check('public_html: build nuevo (manifest igual al del paquete) y cgi-bin, videos, docs intactos', is_file($p.'/build/manifest.json') && hash_file('sha256', $p.'/build/manifest.json') === hash_file('sha256', $l.'/public/build/manifest.json') && is_dir($p.'/cgi-bin') && is_file($p.'/videos/demo.mp4') && is_file($p.'/docs/pdfs/manual.pdf'));
    $s->check('las subidas del admin siguen (public_html/images/products y laravel/public/docs)', is_file($p.'/images/products/1700000000_0_aaaaaaaa.jpg') && is_file($l.'/public/docs/pdfs/subida-dentro-de-laravel-public.pdf'));
    $s->check('storage conservado (el log viejo sigue)', str_contains((string) file_get_contents($l.'/storage/logs/laravel.log'), 'error viejo que no cuenta'));
    $s->check('cachés de config y vistas armadas', is_file($l.'/bootstrap/cache/config.php'));
    $s->check('el sitio está arriba (sin storage/framework/down)', ! is_file($l.'/storage/framework/down') && ! is_file($l.'/storage/framework/maintenance.php'));
    $s->check('GET / → 200 | /productos → 200 | /carrito → 200 | /robots.txt de la app', $s->http('/')['code'] === 200 && $s->http('/productos')['code'] === 200 && $s->http('/carrito')['code'] === 200 && str_contains($s->http('/robots.txt')['body'], 'Sitemap:'));
    $s->check('POST /rukoz/webhook → 404/405 (la ruta no existe: integración apagada)', in_array($s->http('/rukoz/webhook', ['post' => '{}'])['code'], [404, 405], true));
    $b = $s->backups();
    $bd = $b[0] ?? '';
    $s->check('backup creado con todo lo prometido', $bd !== '' && is_file($bd.'/laravel-codigo.zip') && is_file($bd.'/public_html-reemplazados.zip') && is_file($bd.'/base.sql.gz') && is_file($bd.'/env.bak') && is_file($bd.'/public_html-htaccess.copia') && is_file($bd.'/vigencias-antes.txt') && is_dir($bd.'/laravel.viejo/vendor') && is_file($bd.'/deploy.log'), $bd);
    $s->check('el disparo se borró, el zip y el backup se dejan', ! is_file($s->home.'/deploy-cf/disparo.json') && is_file($s->home.'/deploy-cf/paquete.zip'));
    $s->check('work/ se limpió', ! is_dir($s->home.'/deploy-cf/work/laravel.nuevo'));
    $s->check('estado.json trae resumen de pasos y no deja el secreto de bypass', count($e['pasos'] ?? []) >= 15 && ($e['secreto'] ?? null) === null);

    return [$antes, $e];
};

/** Falla inyectada en un punto: tiene que volver atrás y dejar todo igual. */
foreach (['despues_backup', 'despues_mantenimiento', 'despues_codigo', 'despues_migrar', 'verificacion', 'antes_up'] as $pto) {
    $escenarios['falla-'.$pto] = function (Sim $s) use ($pto): void {
        $s->titulo("falla en «{$pto}» — vuelta atrás automática");
        $s->reiniciar();
        $s->disparo('ensayo');
        $s->correrRunner('falla-'.$pto.'-ensayo');
        $antes = $s->foto();
        $s->disparo('deploy');
        [$c, $o, $e] = $s->correrRunner('falla-'.$pto, ['CF_RUNNER_PRUEBA' => 'falla:'.$pto]);
        $despues = $s->foto();
        $s->check('el runner informa la falla (código 1)', $c === 1);
        $esperado = $pto === 'despues_backup' ? 'fallo' : 'vuelta_atras_ok';
        // en «despues_backup» todavía no se tocó nada: no hay nada que deshacer
        $s->check("estado final: {$esperado}", ($e['estado'] ?? '') === $esperado, 'estado '.($e['estado'] ?? '?').' · '.($e['error'] ?? ''));
        $dif = $s->iguales($antes, $despues);
        $s->check('código, vendor, public_html, .env y base IDÉNTICOS a antes del deploy', $dif === [], 'distintos: '.implode(',', $dif));
        $s->check('el sitio quedó arriba y responde 200 como antes (robots.txt estático viejo)', $despues['down'] === 'arriba' && $s->http('/')['code'] === 200 && ! str_contains($s->http('/robots.txt')['body'], 'Sitemap:'));
        $s->check('el disparo se borró', ! is_file($s->home.'/deploy-cf/disparo.json'));
        $s->check('migraciones: ninguna de las nuevas quedó registrada', (int) $s->pdo()->query("SELECT COUNT(*) FROM migrations WHERE migration LIKE '2026_10_19%' OR migration LIKE '2026_10_2%'")->fetchColumn() === 0);
        $bd = $s->backups()[0] ?? '';
        $s->check('el backup se conserva', $pto === 'despues_backup' ? true : ($bd !== '' && is_file($bd.'/base.sql.gz')));
    };
}

/** El proceso muere (sin vuelta atrás): la próxima corrida del cron lo recupera. */
foreach (['despues_mantenimiento', 'despues_codigo', 'despues_migrar'] as $pto) {
    $escenarios['muere-'.$pto] = function (Sim $s) use ($pto): void {
        $s->titulo("el proceso MUERE en «{$pto}» — recuperación en la corrida siguiente");
        $s->reiniciar();
        $s->disparo('ensayo');
        $s->correrRunner('muere-'.$pto.'-ensayo');
        $antes = $s->foto();
        $s->disparo('deploy');
        [$c, , $e] = $s->correrRunner('muere-'.$pto, ['CF_RUNNER_PRUEBA' => 'muere:'.$pto]);
        $s->check('el proceso murió (código 99) dejando el estado «ejecutando»', $c === 99 && ($e['estado'] ?? '') === 'ejecutando');
        $s->check('el sitio quedó en mantenimiento (503 para el público)', $s->http('/')['code'] === 503, 'código '.$s->http('/')['code']);
        [$c2, $o2, $e2] = $s->correrRunner('muere-'.$pto.'-recuperacion');
        $s->check('la corrida siguiente hace la vuelta atrás sola', ($e2['estado'] ?? '') === 'vuelta_atras_ok', 'estado '.($e2['estado'] ?? '?').' · '.($e2['mensaje'] ?? '').' '.($e2['error'] ?? ''));
        $despues = $s->foto();
        $dif = $s->iguales($antes, $despues);
        $s->check('código, public_html, .env y base IDÉNTICOS a antes del deploy', $dif === [], 'distintos: '.implode(',', $dif));
        $s->check('el sitio quedó arriba (200)', $s->http('/')['code'] === 200);
        [$c3] = $s->correrRunner('muere-'.$pto.'-ocioso');
        $s->check('con todo en reposo el runner sale sin hacer nada (código 0, sin salida)', $c3 === 0);
    };
}

$escenarios['rechazos'] = function (Sim $s): void {
    $s->titulo('rechazos — nada se toca');
    $s->reiniciar();
    $antes = $s->foto();
    $uno = static function (Sim $s, string $nombre, callable $arma, string $esperaEnError) use ($antes): void {
        $arma($s);
        [$c, $o, $e] = $s->correrRunner('rechazo-'.$nombre);
        $despues = $s->foto();
        $ok = $c !== 0 && in_array($e['estado'] ?? '', ['rechazado', 'fallo'], true) && str_contains(((string) ($e['error'] ?? '')).' '.($e['mensaje'] ?? ''), $esperaEnError);
        $s->check($nombre, $ok && $s->iguales($antes, $despues) === [] && ! is_file($s->home.'/deploy-cf/disparo.json') && $s->backups() === [], 'estado '.($e['estado'] ?? '?').' · '.($e['error'] ?? ''));
    };
    $uno($s, 'deploy sin ensayo previo', fn (Sim $s) => $s->disparo('deploy'), 'ENSAYO exitoso');
    $uno($s, 'modo desconocido', fn (Sim $s) => $s->disparo('borrar-todo'), 'desconocido');
    $uno($s, 'disparo de hace 5 horas', fn (Sim $s) => $s->disparo('ensayo', ['creado' => gmdate('Y-m-d\TH:i:s\Z', time() - 5 * 3600)]), 'más de 3 horas');
    $uno($s, 'sha256 del zip que no coincide', fn (Sim $s) => $s->disparo('ensayo', ['zip_sha256' => str_repeat('a', 64)]), 'sha256 del paquete NO coincide');
    $uno($s, 'commit que no es el del paquete', fn (Sim $s) => $s->disparo('ensayo', ['commit' => str_repeat('b', 40)]), 'pide');
    // nonce repetido
    $d = $s->disparo('ensayo');
    $s->correrRunner('rechazo-nonce-1');
    file_put_contents($s->home.'/deploy-cf/disparo.json', json_encode($d));
    [$c, , $e] = $s->correrRunner('rechazo-nonce-2');
    $s->check('nonce repetido (el mismo disparo no corre dos veces)', $c !== 0 && ($e['estado'] ?? '') === 'rechazado' && str_contains((string) ($e['error'] ?? ''), 'nonce repetido'));
    // zip adulterado: se cambia un byte después de calcular el sha del disparo
    $zipSim = $s->home.'/deploy-cf/paquete.zip';
    $disp = $s->disparo('ensayo');
    $bytes = file_get_contents($zipSim);
    $bytes[(int) (strlen($bytes) / 2)] = $bytes[(int) (strlen($bytes) / 2)] === 'A' ? 'B' : 'A';
    file_put_contents($zipSim, $bytes);
    [$c, , $e] = $s->correrRunner('rechazo-zip-adulterado');
    $s->check('zip adulterado (un byte distinto)', $c !== 0 && str_contains((string) ($e['error'] ?? ''), 'sha256'));
    // zips armados a propósito con entradas prohibidas (y con manifiesto + sha consistentes: sólo las listas negras los frenan)
    foreach (['laravel/.env' => '.env', 'public_html/index.php' => 'lista de estáticos', 'public_html/images/products/x.php' => 'ejecutable', 'laravel/storage/logs/x.log' => 'storage', 'laravel/../../etc/passwd' => 'ruta insegura', 'public_html/cgi-bin/x.txt' => 'carpeta del hosting'] as $entrada => $motivo) {
        copy($s->zip, $zipSim);
        $z = new ZipArchive();
        $z->open($zipSim);
        $m = json_decode((string) $z->getFromName('manifest.json'), true);
        $z->addFromString($entrada, 'contenido');
        $m['archivos'][] = ['r' => $entrada, 's' => hash('sha256', 'contenido'), 'b' => 9];
        $z->addFromString('manifest.json', json_encode($m));
        $z->close();
        $s->disparo('ensayo');
        [$c, , $e] = $s->correrRunner('rechazo-entrada-'.md5($entrada));
        $s->check("entrada prohibida «{$entrada}»", $c !== 0 && str_contains((string) ($e['error'] ?? ''), 'prohibida'), (string) ($e['error'] ?? ''));
    }
    copy($s->zip, $zipSim);
    // manifiesto con un hash que no es el del archivo
    $z = new ZipArchive();
    $z->open($zipSim);
    $m = json_decode((string) $z->getFromName('manifest.json'), true);
    $m['archivos'][0]['s'] = str_repeat('0', 64);
    $z->addFromString('manifest.json', json_encode($m));
    $z->close();
    $s->disparo('ensayo');
    [$c, , $e] = $s->correrRunner('rechazo-hash-archivo');
    $s->check('archivo cuyo sha256 no coincide con el manifiesto', $c !== 0 && str_contains((string) ($e['error'] ?? ''), 'no coincide con el manifiesto'), (string) ($e['error'] ?? ''));
    copy($s->zip, $zipSim);
    $s->check('después de todos los rechazos el sitio sigue idéntico', $s->iguales($antes, $s->foto()) === []);
};

$escenarios['migraciones-raras'] = function (Sim $s): void {
    $s->titulo('base atrasada: una migración pendiente que el manifiesto no espera');
    $s->reiniciar();
    // se borra de la tabla una migración vieja: aparece como pendiente y el manifiesto no la espera
    $s->pdo()->exec("DELETE FROM migrations WHERE migration = '2024_12_22_000001_create_categories_table'");
    $antes = $s->foto();
    $s->disparo('ensayo');
    [$c, , $e] = $s->correrRunner('migraciones-raras');
    $s->check('el ensayo frena antes de tocar nada', $c !== 0 && str_contains((string) ($e['error'] ?? ''), 'migraciones pendientes que el manifiesto no espera'), (string) ($e['error'] ?? ''));
    $s->check('el sitio sigue idéntico', $s->iguales($antes, $s->foto()) === []);
};

$escenarios['reintento'] = function (Sim $s): void {
    $s->titulo('reintento — una falla, vuelta atrás, y el deploy siguiente sale bien (la migración de +3 h corre una sola vez)');
    $s->reiniciar();
    $s->disparo('ensayo');
    $s->correrRunner('reintento-ensayo');
    $s->disparo('deploy');
    [$c, , $e] = $s->correrRunner('reintento-1', ['CF_RUNNER_PRUEBA' => 'falla:despues_migrar']);
    $s->check('primer intento: falla después de migrar y vuelve atrás', $c === 1 && ($e['estado'] ?? '') === 'vuelta_atras_ok');
    $vig = $s->pdo()->query('SELECT start_date FROM product_offers ORDER BY id LIMIT 1')->fetchColumn();
    $s->check('la base volvió a las vigencias originales (2026-10-31 00:00:00)', $vig === '2026-10-31 00:00:00', (string) $vig);
    $s->disparo('deploy');
    [$c, , $e] = $s->correrRunner('reintento-2');
    $s->check('segundo intento: deploy OK sin repetir el ensayo (sigue vigente)', $c === 0 && ($e['estado'] ?? '') === 'ok', ($e['error'] ?? '').' '.($e['mensaje'] ?? ''));
    $vig = $s->pdo()->query('SELECT start_date FROM product_offers ORDER BY id LIMIT 1')->fetchColumn();
    $s->check('vigencia +3 h aplicada UNA sola vez (2026-10-31 03:00:00)', $vig === '2026-10-31 03:00:00', (string) $vig);
    $s->check('hay dos carpetas de backup (la del intento fallido y la del bueno)', count($s->backups()) === 2);
};

$escenarios['htaccess'] = function (Sim $s): void {
    $s->titulo('.htaccess — bloques S.2 aceptados por el servidor');
    $s->reiniciar();
    $htAntes = (string) file_get_contents($s->home.'/public_html/.htaccess');
    $s->disparo('ensayo');
    $s->correrRunner('htaccess-ensayo');
    $s->disparo('deploy', ['htaccess' => 'merge']);
    [$c, , $e] = $s->correrRunner('htaccess');
    $ht = (string) file_get_contents($s->home.'/public_html/.htaccess');
    $s->check('deploy OK', $c === 0 && ($e['estado'] ?? '') === 'ok', (string) ($e['error'] ?? ''));
    $s->check('el .htaccess ahora trae los dos bloques S.2 ARRIBA y conserva lo de antes', str_contains($ht, '# --- Archivos que nunca se sirven') && str_contains($ht, '# --- Cabeceras') && str_ends_with($ht, $htAntes) && strpos($ht, '# --- Archivos') < strpos($ht, '# htaccess del hosting (sim)'));
    $s->check('copia del .htaccess anterior en el backup', is_file(($s->backups()[0] ?? '').'/public_html-htaccess.copia') && file_get_contents(($s->backups()[0] ?? '').'/public_html-htaccess.copia') === $htAntes);
};

$escenarios['htaccess-500'] = function (Sim $s): void {
    $s->titulo('.htaccess — el servidor rechaza los bloques S.2 (500): se vuelve al de antes y el deploy sigue');
    putenv('SIM_HTACCESS_500=1');
    $s->reiniciar();
    $htAntes = (string) file_get_contents($s->home.'/public_html/.htaccess');
    $s->disparo('ensayo');
    $s->correrRunner('htaccess500-ensayo');
    $s->disparo('deploy', ['htaccess' => 'merge']);
    [$c, , $e] = $s->correrRunner('htaccess500');
    putenv('SIM_HTACCESS_500=0');
    $s->check('el deploy termina OK igual', $c === 0 && ($e['estado'] ?? '') === 'ok', (string) ($e['error'] ?? ''));
    $s->check('el .htaccess quedó EXACTAMENTE como estaba', (string) file_get_contents($s->home.'/public_html/.htaccess') === $htAntes);
    $s->check('hay un aviso y un pendiente manual sobre el .htaccess', str_contains(json_encode($e['avisos'] ?? []), 'htaccess') && str_contains(json_encode($e['manual'] ?? []), 'htaccess'));
    $s->detenerServidor();
};

$escenarios['reposo'] = function (Sim $s): void {
    $s->titulo('reposo — sin disparo el runner no hace nada');
    $s->reiniciar();
    $antes = $s->foto();
    $log0 = $s->ultimoRunnerLog();
    [$c, $o] = $s->correrRunner('reposo');
    $s->check('sale con código 0 y sin escribir nada', $c === 0 && trim($o) === '' && $s->ultimoRunnerLog() === $log0 && $s->iguales($antes, $s->foto()) === []);
};

$escenarios['concurrencia'] = function (Sim $s): void {
    $s->titulo('concurrencia — dos corridas a la vez (flock)');
    $s->reiniciar();
    $lock = fopen($s->home.'/deploy-cf/runner.lock', 'c');
    flock($lock, LOCK_EX);
    $s->disparo('ensayo');
    [$c, $o] = $s->correrRunner('concurrencia');
    $s->check('con el lock tomado por otra corrida, ésta sale sin hacer nada y el disparo queda para después', $c === 0 && trim($o) === '' && is_file($s->home.'/deploy-cf/disparo.json'));
    flock($lock, LOCK_UN);
    fclose($lock);
};

/** El punto de entrada web: sin token no hace nada; con token y sin trabajo, 204. */
$escenarios['entrada'] = function (Sim $s): void {
    $s->titulo('entrada — el punto de entrada web: 404 sin token, 204 con token');
    $s->reiniciar();
    $url = '/'.$s->stub;
    $r = $s->http($url);
    $s->check('sin token → 404 y cuerpo vacío', $r['code'] === 404 && $r['body'] === '', 'código '.$r['code']);
    foreach (['?t=malo', '?t=', '?t[]=x', '?t='.$s->token.'x', '?T='.$s->token] as $q) {
        $r = $s->http($url.$q);
        $s->check("token inválido ({$q}) → 404 vacío", $r['code'] === 404 && $r['body'] === '', 'código '.$r['code']);
    }
    $r = $s->http($url.'?t='.$s->token.'&ping=1');
    $s->check('token válido + ping → 204 vacío', $r['code'] === 204 && $r['body'] === '', 'código '.$r['code']);
    // con trabajo pendiente pero SIN token: no se ejecuta nada y el disparo queda intacto
    $s->disparo('ensayo');
    $log0 = $s->ultimoRunnerLog();
    $r = $s->http($url.'?t=malo');
    $s->check('hay disparo + token inválido → 404, el disparo sigue ahí y el log no se movió', $r['code'] === 404 && is_file($s->home.'/deploy-cf/disparo.json') && $s->ultimoRunnerLog() === $log0);
    // la plantilla sin instalar (con __TOKEN_SHA256__) no deja pasar a nadie
    copy($s->repo.'/deploy/entrada.php', $s->home.'/public_html/cf-deploy-ffffffffffffffff.php');
    foreach (['__TOKEN_SHA256__', '', hash('sha256', '')] as $t) {
        $r = $s->http('/cf-deploy-ffffffffffffffff.php?t='.$t);
        $s->check('la plantilla sin instalar rechaza «'.substr($t, 0, 16).'»', $r['code'] === 404);
    }
    unlink($s->home.'/public_html/cf-deploy-ffffffffffffffff.php');
    // con token y SIN trabajo: 204, marca de vida del cron, y nada más
    unlink($s->home.'/deploy-cf/disparo.json');
    $r = $s->http($url.'?t='.$s->token);
    $s->check('token válido y sin trabajo → 204, deja cron-ultimo.txt y no escribe el log', $r['code'] === 204 && is_file($s->home.'/deploy-cf/cron-ultimo.txt') && $s->ultimoRunnerLog() === $log0, 'código '.$r['code']);
    $s->check('el sitio sigue respondiendo 200', $s->http('/')['code'] === 200);
};

/** El deploy entero, cortado en puntos de control cada 2 pasos y encadenado (el simulador hace de pedido encadenado). */
$escenarios['segmentos'] = function (Sim $s) use ($escenarios): void {
    [, $e] = $escenarios['deploy']($s, 'segmentos', [], ['CF_RUNNER_PRUEBA' => 'segmento:2']);
    $log = $s->ultimoRunnerLog();
    $s->check('se cortó en varios segmentos (≥ 4) y todos los pasos quedaron hechos', ($e['segmento'] ?? 0) >= 4 && in_array('VENTANA', (array) ($e['pasos_ok'] ?? []), true) && in_array('B1', (array) ($e['pasos_ok'] ?? []), true), 'segmento '.($e['segmento'] ?? '?'));
    $s->check('el log muestra puntos de control y el encadenado', substr_count($log, 'Punto de control del segmento') >= 4 && str_contains($log, 'encadenado simulado'));
    $s->check('la ventana de mantenimiento empezó en un segmento nuevo (punto de control justo antes de VENTANA)', str_contains($log, 'sigue «VENTANA»'));
    $s->check('un solo backup (los pasos reanudados no duplican)', count($s->backups()) === 1);
    $s->check('la ventana (mantenimiento puesto y sacado) se midió en un solo segmento', ($e['ventana_seg'] ?? -1) >= 0);
};

/** El proceso MUERE en un paso de las fases A/B (el sitio no se tocó): la corrida siguiente reanuda y termina. */
foreach ([['ensayo', 'paso_A2'], ['ensayo', 'fin_A3'], ['ensayo', 'paso_A5'], ['ensayo', 'fin_A7'], ['ensayo', 'paso_W1'], ['deploy', 'fin_A5'], ['deploy', 'fin_B1'], ['deploy', 'paso_VENTANA']] as [$modo, $pto]) {
    $escenarios['muere-seg-'.$modo.'-'.$pto] = function (Sim $s) use ($modo, $pto): void {
        $s->titulo("el proceso MUERE en «{$pto}» ({$modo}) — la corrida siguiente reanuda");
        $s->reiniciar();
        if ($modo === 'deploy') {
            $s->disparo('ensayo');
            $s->correrRunner('muere-seg-'.$pto.'-ensayo');
        }
        $antes = $s->foto();
        $s->disparo($modo);
        [$c, , $e] = $s->correrRunner('muere-seg-'.$modo.'-'.$pto, ['CF_RUNNER_PRUEBA' => 'muere:'.$pto]);
        $s->check('el proceso murió (código 99) dejando «ejecutando» y sin punto de control', $c === 99 && ($e['estado'] ?? '') === 'ejecutando' && empty($e['en_pausa']), 'código '.$c.' estado '.($e['estado'] ?? '?'));
        $s->check('el sitio no se tocó: arriba, 200 y todo idéntico', $s->iguales($antes, $s->foto()) === [] && $s->http('/')['code'] === 200);
        [$c2, , $e2] = $s->correrRunner('muere-seg-'.$modo.'-'.$pto.'-reanuda');
        $s->check('la corrida siguiente reanuda y termina bien', $c2 === 0 && ($e2['estado'] ?? '') === 'ok', 'código '.$c2.' estado '.($e2['estado'] ?? '?').' · '.($e2['error'] ?? ''));
        $s->check('lo registra: «se reanuda desde el último paso hecho», recuperaciones ≥ 1, segmento ≥ 2', str_contains($s->ultimoRunnerLog(), 'se reanuda desde el último paso hecho') && ($e2['recuperaciones'] ?? 0) >= 1 && ($e2['segmento'] ?? 0) >= 2);
        $s->check('el disparo se borró', ! is_file($s->home.'/deploy-cf/disparo.json'));
        if ($modo === 'deploy') {
            $l = $s->home.'/laravel';
            $s->check('el código nuevo quedó puesto y el sitio responde 200', is_file($l.'/.cf-version.json') && (json_decode((string) file_get_contents($l.'/.cf-version.json'), true)['commit'] ?? '') === $s->commit() && $s->http('/')['code'] === 200);
            $s->check('un solo backup aunque el paso B1 se haya repetido', count($s->backups()) === 1, (string) count($s->backups()));
        } else {
            $s->check('el ensayo no tocó nada y registra ensayo_ok', $s->iguales($antes, $s->foto()) === [] && isset($e2['ensayo_ok']['zip_sha256']));
        }
    };
}

/** Muere siempre en el mismo paso: tras MAX_INTENTOS se abandona (no hay bucle infinito). */
$escenarios['muere-bucle'] = function (Sim $s): void {
    $s->titulo('el proceso muere SIEMPRE en el mismo paso — se abandona tras 3 intentos');
    $s->reiniciar();
    $antes = $s->foto();
    $s->disparo('ensayo');
    for ($i = 1; $i <= 3; $i++) {
        [$c] = $s->correrRunner('muere-bucle-'.$i, ['CF_RUNNER_PRUEBA' => 'muere:paso_A5']);
        $s->check("intento {$i}: muere (99)", $c === 99, "código {$c}");
    }
    [$c, , $e] = $s->correrRunner('muere-bucle-4', ['CF_RUNNER_PRUEBA' => 'muere:paso_A5']);
    $s->check('intento 4: no vuelve a correr el paso, se abandona (estado fallo)', $c === 1 && ($e['estado'] ?? '') === 'fallo' && str_contains((string) ($e['error'] ?? ''), 'se abandona'), 'código '.$c.' · '.($e['error'] ?? ''));
    $s->check('el disparo se borró y el sitio quedó idéntico', ! is_file($s->home.'/deploy-cf/disparo.json') && $s->iguales($antes, $s->foto()) === []);
    [$c5] = $s->correrRunner('muere-bucle-ocioso');
    $s->check('después, con todo en reposo, sale sin hacer nada', $c5 === 0);
};

/** La ventana de mantenimiento tiene tope: pasado, se aborta y se vuelve atrás. */
$escenarios['tope-ventana'] = function (Sim $s): void {
    $s->titulo('tope de la ventana de mantenimiento — se aborta y se vuelve atrás');
    $s->reiniciar();
    $s->disparo('ensayo');
    $s->correrRunner('tope-ensayo');
    $antes = $s->foto();
    $s->disparo('deploy');
    [$c, , $e] = $s->correrRunner('tope-deploy', ['CF_RUNNER_PRUEBA' => 'tope:2']);
    $s->check('el deploy se aborta por el tope y vuelve atrás', $c === 1 && ($e['estado'] ?? '') === 'vuelta_atras_ok' && str_contains((string) ($e['error'] ?? ''), 'tope'), 'código '.$c.' estado '.($e['estado'] ?? '?').' · '.($e['error'] ?? ''));
    $s->check('código, public_html, .env y base IDÉNTICOS a antes y el sitio arriba', $s->iguales($antes, $s->foto()) === [] && $s->http('/')['code'] === 200);
};

/** Un disparo nuevo subido mientras otra corrida está en pausa NO se pierde ni se borra con el final de la primera. */
$escenarios['disparo-en-pausa'] = function (Sim $s): void {
    $s->titulo('disparo nuevo durante una corrida en pausa — espera su turno');
    $s->reiniciar();
    $d1 = $s->disparo('ensayo');
    [$c, , $e] = $s->correrRunner('pausa-1', ['CF_RUNNER_PRUEBA' => 'segmento:3', 'SIM_UNA_VEZ' => '1']);
    $s->check('la primera corrida queda en pausa (ejecutando + en_pausa)', $c === 0 && ($e['estado'] ?? '') === 'ejecutando' && ! empty($e['en_pausa']));
    $d2 = $s->disparo('ensayo');
    $s->check('el disparo nuevo tiene otro nonce', $d2['nonce'] !== $d1['nonce']);
    [$c, , $e] = $s->correrRunner('pausa-2', ['CF_RUNNER_PRUEBA' => 'segmento:3']);
    $s->check('la primera corrida termina con SU nonce', $c === 0 && ($e['estado'] ?? '') === 'ok' && ($e['nonce'] ?? '') === $d1['nonce'], 'nonce '.substr((string) ($e['nonce'] ?? ''), 0, 8));
    $quedo = json_decode((string) @file_get_contents($s->home.'/deploy-cf/disparo.json'), true);
    $s->check('el disparo nuevo sigue esperando en disparo.json', ($quedo['nonce'] ?? '') === $d2['nonce']);
    [$c, , $e] = $s->correrRunner('pausa-3');
    $s->check('y la corrida siguiente lo ejecuta', $c === 0 && ($e['estado'] ?? '') === 'ok' && ($e['nonce'] ?? '') === $d2['nonce'] && ! is_file($s->home.'/deploy-cf/disparo.json'));
};

/** opcache (simulada): después del cambio de carpetas se invalida cada .php del árbol nuevo, y también en la vuelta atrás. */
$escenarios['opcache'] = function (Sim $s) use ($escenarios): void {
    $escenarios['deploy']($s, 'opcache');
    $log = $s->ultimoRunnerLog();
    $lineas = array_filter(array_map('trim', file($s->opcacheLog()) ?: []));
    $inv = [];
    $sinForce = 0;
    foreach ($lineas as $l) {
        if (! str_starts_with($l, 'F ')) {
            $sinForce++;
        }
        $inv[strtolower(substr($l, 2))] = true;
    }
    $s->check('todas las invalidaciones son con force=true', $lineas !== [] && $sinForce === 0, count($lineas).' llamadas, '.$sinForce.' sin force');
    $faltan = [];
    $total = 0;
    foreach (['app', 'config', 'routes', 'bootstrap', 'vendor/laravel', 'vendor/composer', 'vendor/autoload.php'] as $sub) {
        $ruta = $s->home.'/laravel/'.$sub;
        $it = is_dir($ruta) ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ruta, FilesystemIterator::SKIP_DOTS)) : (is_file($ruta) ? [new SplFileInfo($ruta)] : []);
        foreach ($it as $f) {
            if ($f->isFile() && str_ends_with($f->getFilename(), '.php')) {
                $total++;
                if (! isset($inv[strtolower(str_replace('\\', '/', $f->getPathname()))])) {
                    $faltan[] = $f->getPathname();
                }
            }
        }
    }
    $s->check("cada .php del Laravel nuevo (app, config, routes, bootstrap, vendor/laravel y composer: {$total}) fue invalidado", $total > 100 && $faltan === [], 'faltan '.count($faltan).': '.implode(', ', array_slice($faltan, 0, 3)));
    $p1 = strpos($log, 'opcache: ');
    $p2 = strpos($log, 'artisan package:discover');
    $s->check('la invalidación ocurre ANTES de cargar el Laravel nuevo (antes de package:discover)', $p1 !== false && $p2 !== false && $p1 < $p2);
    $s->check('y después de las cachés se invalidan bootstrap/cache y las vistas, y se espera', str_contains($log, 'bootstrap/cache (') && str_contains($log, 'opcache: espero'));
    // vuelta atrás: también invalida el árbol viejo
    $s->reiniciar();
    $s->disparo('ensayo');
    $s->correrRunner('opcache-ensayo2');
    $s->disparo('deploy');
    $s->correrRunner('opcache-falla', ['CF_RUNNER_PRUEBA' => 'falla:despues_migrar']);
    $log = $s->ultimoRunnerLog();
    $pv = strrpos($log, '--- VUELTA ATRÁS ---');
    $s->check('en la vuelta atrás también se invalida el código (y se espera) antes de subir el sitio', $pv !== false && strpos($log, 'opcache: ', $pv) !== false && strpos($log, 'opcache: espero', $pv) !== false);
};

/** APP_URL en http: el ensayo avisa «se va a corregir» sin fallar; el deploy la corrige a https; la vuelta atrás restaura el .env. */
$escenarios['app-url-http'] = function (Sim $s): void {
    $s->titulo('APP_URL en http — se verifica https, se corrige en el .env nuevo y la vuelta atrás la restaura');
    $s->reiniciar();
    $s->envSet(['APP_URL' => 'http://chispasfrias.com']);
    $prueba = ['CF_RUNNER_HTTPS_PRUEBA' => 'ok', 'CF_RUNNER_URL_PRUEBA' => 'http://127.0.0.1:'.$s->port, 'CF_RUNNER_REDIRECCION_PRUEBA' => 'ok'];
    $antes = $s->foto();
    $s->disparo('ensayo');
    [$c, , $e] = $s->correrRunner('app-url-ensayo', $prueba);
    $s->check('el ensayo NO falla por APP_URL en http', $c === 0 && ($e['estado'] ?? '') === 'ok', 'estado '.($e['estado'] ?? '?').' · '.($e['error'] ?? ''));
    $s->check('y avisa «SE VA A CORREGIR» a https://chispasfrias.com', str_contains(json_encode($e['avisos'] ?? [], JSON_UNESCAPED_UNICODE), 'SE VA A CORREGIR') && ($e['app_url_nueva'] ?? '') === 'https://chispasfrias.com');
    $s->check('el ensayo no tocó el .env ni nada (idéntico a antes)', $s->iguales($antes, $s->foto()) === []);
    // deploy que falla después de migrar: vuelta atrás, el .env vuelve a http
    $s->disparo('deploy');
    [$c, , $e] = $s->correrRunner('app-url-falla', array_merge($prueba, ['CF_RUNNER_PRUEBA' => 'falla:despues_migrar']));
    $s->check('deploy con falla: vuelta atrás OK', $c === 1 && ($e['estado'] ?? '') === 'vuelta_atras_ok', 'estado '.($e['estado'] ?? '?').' · '.($e['error'] ?? ''));
    $s->check('el .env quedó EXACTAMENTE como antes (APP_URL http) y todo idéntico', $s->iguales($antes, $s->foto()) === [] && preg_match('/^APP_URL=http:\/\/chispasfrias\.com$/m', (string) file_get_contents($s->home.'/laravel/.env')) === 1);
    // deploy bueno
    $s->disparo('deploy');
    [$c, , $e] = $s->correrRunner('app-url-deploy', $prueba);
    $s->check('deploy OK', $c === 0 && ($e['estado'] ?? '') === 'ok', 'estado '.($e['estado'] ?? '?').' · '.($e['error'] ?? ''));
    $env = (string) file_get_contents($s->home.'/laravel/.env');
    $s->check('el .env nuevo tiene APP_URL=https://chispasfrias.com y el resto igual', preg_match('/^APP_URL=https:\/\/chispasfrias\.com$/m', $env) === 1 && str_contains($env, 'OTRA_VARIABLE_QUE_NO_SE_TOCA="con espacios y # numeral"') && str_contains($env, 'DB_DATABASE=cf_deploy_test') && preg_match('/^RUKOZ_INTEGRACION=false$/m', $env) === 1);
    $bak = glob($s->home.'/backups-cf/*/env.bak') ?: [];
    $s->check('el .env anterior (APP_URL http) está en el backup', $bak !== [] && str_contains((string) file_get_contents(end($bak)), 'APP_URL=http://chispasfrias.com'));
    $s->check('el mensaje final trae la verificación: http://chispasfrias.com/ → 301 → https://chispasfrias.com/ (y ya no queda un pendiente manual)', str_contains((string) ($e['mensaje'] ?? ''), 'Redirección http→https verificada: http://chispasfrias.com/ → 301 → https://chispasfrias.com/') && ! str_contains(json_encode($e['manual'] ?? [], JSON_UNESCAPED_UNICODE), 'redirige'), (string) ($e['mensaje'] ?? ''));
    $s->check('la tienda responde 200 y el sitio está arriba', $s->http('/')['code'] === 200 && ! is_file($s->home.'/laravel/storage/framework/down'));
};

/** APP_URL en http y https no verificable: no se corrige a ciegas, el ensayo falla (sin tocar nada). */
$escenarios['app-url-sin-https'] = function (Sim $s): void {
    $s->titulo('APP_URL en http y https sin certificado válido — no se corrige a ciegas');
    $s->reiniciar();
    $s->envSet(['APP_URL' => 'http://chispasfrias.com']);
    $antes = $s->foto();
    $s->disparo('ensayo');
    [$c, , $e] = $s->correrRunner('app-url-sin-https', ['CF_RUNNER_HTTPS_PRUEBA' => 'falla', 'CF_RUNNER_URL_PRUEBA' => 'http://127.0.0.1:'.$s->port]);
    $s->check('el ensayo falla informando que no se puede corregir sola', $c !== 0 && ($e['estado'] ?? '') === 'fallo' && str_contains((string) ($e['error'] ?? ''), 'no se puede corregir sola'), (string) ($e['error'] ?? ''));
    $s->check('no se tocó nada', $s->iguales($antes, $s->foto()) === [] && ! is_file($s->home.'/deploy-cf/disparo.json'));
};

/** Si después del deploy http:// no da 301 a https://, el deploy falla y vuelve atrás (la tienda quedaría rota para quien entre por http). */
$escenarios['redireccion-falla'] = function (Sim $s): void {
    $s->titulo('http:// no redirige a https:// después del deploy — vuelta atrás');
    $s->reiniciar();
    $s->envSet(['APP_URL' => 'http://chispasfrias.com']);
    $prueba = ['CF_RUNNER_HTTPS_PRUEBA' => 'ok', 'CF_RUNNER_URL_PRUEBA' => 'http://127.0.0.1:'.$s->port];
    $s->disparo('ensayo');
    $s->correrRunner('redireccion-ensayo', $prueba);
    $antes = $s->foto();
    $s->disparo('deploy');
    [$c, , $e] = $s->correrRunner('redireccion-falla', array_merge($prueba, ['CF_RUNNER_REDIRECCION_PRUEBA' => 'falla']));
    $s->check('el deploy falla informando que http no dio 301 y vuelve atrás', $c === 1 && ($e['estado'] ?? '') === 'vuelta_atras_ok' && str_contains((string) ($e['error'] ?? ''), 'tenía que dar 301'), 'estado '.($e['estado'] ?? '?').' · '.($e['error'] ?? ''));
    $s->check('código, public_html, .env (APP_URL http otra vez) y base IDÉNTICOS a antes y el sitio arriba', $s->iguales($antes, $s->foto()) === [] && $s->http('/')['code'] === 200);
};

/** La URL corregida sobrevive a los cortes en segmentos (cada segmento es un proceso nuevo que relee el .env, que todavía dice http). */
$escenarios['app-url-segmentos'] = function (Sim $s): void {
    $s->titulo('APP_URL en http con el deploy cortado en segmentos');
    $s->reiniciar();
    $s->envSet(['APP_URL' => 'http://chispasfrias.com']);
    $prueba = ['CF_RUNNER_HTTPS_PRUEBA' => 'ok', 'CF_RUNNER_URL_PRUEBA' => 'http://127.0.0.1:'.$s->port, 'CF_RUNNER_PRUEBA' => 'segmento:2'];
    $s->disparo('ensayo');
    [$c, , $e] = $s->correrRunner('app-url-seg-ensayo', $prueba);
    $s->check('ensayo en segmentos OK', $c === 0 && ($e['estado'] ?? '') === 'ok' && ($e['segmento'] ?? 0) >= 3, 'estado '.($e['estado'] ?? '?').' · '.($e['error'] ?? ''));
    $s->disparo('deploy');
    [$c, , $e] = $s->correrRunner('app-url-seg-deploy', $prueba);
    $s->check('deploy en segmentos OK', $c === 0 && ($e['estado'] ?? '') === 'ok' && ($e['segmento'] ?? 0) >= 4, 'estado '.($e['estado'] ?? '?').' · '.($e['error'] ?? ''));
    $s->check('APP_URL quedó en https y la tienda responde 200', preg_match('/^APP_URL=https:\/\/chispasfrias\.com$/m', (string) file_get_contents($s->home.'/laravel/.env')) === 1 && $s->http('/')['code'] === 200);
};

/** El proceso muere con el .env ya corregido: la corrida siguiente vuelve atrás y verifica la tienda por la URL que se usó. */
$escenarios['app-url-muere'] = function (Sim $s): void {
    $s->titulo('APP_URL corregida y el proceso muere en la ventana — recuperación');
    $s->reiniciar();
    $s->envSet(['APP_URL' => 'http://chispasfrias.com']);
    $prueba = ['CF_RUNNER_HTTPS_PRUEBA' => 'ok', 'CF_RUNNER_URL_PRUEBA' => 'http://127.0.0.1:'.$s->port];
    $s->disparo('ensayo');
    $s->correrRunner('app-url-muere-ensayo', $prueba);
    $antes = $s->foto();
    $s->disparo('deploy');
    [$c, , $e] = $s->correrRunner('app-url-muere', array_merge($prueba, ['CF_RUNNER_PRUEBA' => 'muere:despues_migrar']));
    $s->check('el proceso murió con la ventana abierta', $c === 99 && ($e['estado'] ?? '') === 'ejecutando');
    $s->check('el .env ya tenía la URL corregida', preg_match('/^APP_URL=https:\/\/chispasfrias\.com$/m', (string) file_get_contents($s->home.'/laravel/.env')) === 1);
    [, , $e2] = $s->correrRunner('app-url-muere-recuperacion', $prueba);
    $s->check('la corrida siguiente vuelve atrás y verifica la tienda', ($e2['estado'] ?? '') === 'vuelta_atras_ok', 'estado '.($e2['estado'] ?? '?').' · '.($e2['mensaje'] ?? ''));
    $s->check('todo idéntico a antes, con el .env otra vez en http', $s->iguales($antes, $s->foto()) === [] && $s->http('/')['code'] === 200);
};

/** A4 junta TODOS los problemas del .env y los informa juntos. */
$escenarios['a4-problemas'] = function (Sim $s): void {
    $s->titulo('A4 — todos los problemas del .env juntos, de una sola vez');
    $s->reiniciar();
    $s->envSet(['APP_ENV' => 'local', 'APP_DEBUG' => 'true', 'APP_KEY' => '', 'DB_USERNAME' => 'usuario_que_no_existe', 'APP_MAINTENANCE_DRIVER' => 'database', 'APP_URL' => 'ftp://chispasfrias.com']);
    $antes = $s->foto();
    $s->disparo('ensayo');
    [$c, $o, $e] = $s->correrRunner('a4-problemas');
    $err = (string) ($e['error'] ?? '');
    $s->check('el ensayo frena en A4 con UN solo informe de 6 problemas', $c !== 0 && ($e['estado'] ?? '') === 'fallo' && str_contains($err, 'Se encontraron 6 problema(s)'), $err);
    foreach (['APP_ENV es «local»', 'APP_DEBUG es «true»', 'Falta APP_KEY', 'No puedo entrar a la base', 'APP_MAINTENANCE_DRIVER es «database»', 'no es una URL http(s) válida'] as $frag) {
        $s->check("el informe incluye: {$frag}", str_contains($err, $frag));
    }
    $s->check('y el log los marca uno por uno como PROBLEMA 1…6', substr_count($o, 'PROBLEMA ') === 6);
    $s->check('no se tocó nada', $s->iguales($antes, $s->foto()) === []);
};

$comando = $args[0] ?? 'ayuda';
try {
    if ($comando === 'preparar') {
        $sim->escribirIni();
        $sim->preparar();
    } elseif ($comando === 'escenario') {
        $nombre = $args[1] ?? '';
        if (! isset($escenarios[$nombre])) {
            fwrite(STDERR, 'Escenarios: '.implode(', ', array_keys($escenarios))."\n");
            exit(2);
        }
        $escenarios[$nombre]($sim);
    } elseif ($comando === 'servir') {
        // Deja el sitio simulado de hoy arriba (para probar publicar.ps1 de punta a punta) hasta que aparezca base/servir.stop
        $sim->reiniciar();
        @unlink($sim->base.'/servir.stop');
        echo "sitio simulado arriba en http://127.0.0.1:{$sim->port}
";
        while (! is_file($sim->base.'/servir.stop')) {
            sleep(2);
        }
    } elseif ($comando === 'todo') {
        foreach ($escenarios as $nombre => $f) {
            $f($sim);
        }
    } else {
        echo "Uso: php simulacion.php preparar|escenario <nombre>|todo --zip=<paquete.zip> [--base=C:/cf-test/sim]\nEscenarios: ".implode(', ', array_keys($escenarios))."\n";
        exit(2);
    }
} finally {
    $sim->detenerServidor();
}

$ok = count(array_filter($sim->resultados, static fn (array $r): bool => $r[1]));
$mal = count($sim->resultados) - $ok;
echo "\n\033[1m".($mal === 0 ? "\033[32m" : "\033[31m")."RESULTADO: {$ok} pruebas OK, {$mal} fallaron\033[0m\n";
exit($mal === 0 ? 0 : 1);
