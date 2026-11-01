<?php
/**
 * deploy/runner.php — deploy de Chispas Frías en DonWeb/Ferozo SIN SSH y SIN procesos externos.
 *
 * Lo corre el cron de Ferozo cada 5 minutos:      /opt/php8-2/bin/php /home/c2731764/deploy-cf/runner.php
 * Vive en ~/deploy-cf/ (fuera de public_html) junto con lo que sube deploy/publicar.ps1 por FTP:
 *
 *   disparo.json   el pedido de trabajo (modo ensayo|deploy, commit, sha256 del paquete, nonce). Sin este archivo el runner
 *                  sale sin hacer nada. Se borra siempre al terminar (bien o mal): un disparo viejo nunca se repite.
 *   paquete.zip    laravel/ (código + vendor), public_html/ (build y estáticos) y manifest.json (commit + sha256 de cada archivo).
 *   estado.json    lo que lee publicar.ps1 por FTP (paso actual, resultado, resumen).   runner.log   el log.
 *
 * No usa exec/shell_exec/proc_open/putenv/eval ni nada de lo que el hosting deshabilita. Los comandos de artisan se corren en este
 * mismo proceso (Kernel::call). Truco que evita separar el trabajo en dos ejecuciones del cron: ANTES del cambio de código este
 * proceso no carga nada de Laravel (backup, mantenimiento y volcado de la base son PHP puro: PDO, ZipArchive, rename); recién DESPUÉS
 * de cambiar las carpetas se carga el Laravel NUEVO, por primera y única vez en el proceso.
 *
 * Fases (cada una deja un punto de control en estado.json; si el proceso muere a mitad, la próxima corrida del cron lo detecta y
 * hace la vuelta atrás sola):
 *   A  verificaciones (las hace también el ensayo): CLI, disparo, paquete, hashes, base, espacio, estructura, HTTP de hoy.
 *   B  backup de archivos (sitio todavía arriba): ~/backups-cf/<fecha>/
 *   C  mantenimiento (503 prerenderizado + clave de bypass) · volcado de la base · cambio de código por renombre de carpetas
 *   D  Laravel nuevo en este proceso: package:discover · migrate --force · cachés
 *   E  verificación HTTP con el bypass · .env sin integraciones · log sin errores nuevos
 *   F  sitio arriba · comprobación pública · .env a 600 · limpieza
 *   Ante cualquier falla después de C: vuelta atrás (carpetas, base, .env, .htaccess), sitio arriba y verificado.
 *
 * Prueba local: variable de entorno CF_RUNNER_PRUEBA=falla:<punto> (lanza un error en ese punto) o muere:<punto> (corta el proceso
 * sin vuelta atrás, para probar la recuperación). Sin esa variable, que en el hosting no existe, no hace nada.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

final class CfFallo extends RuntimeException
{
}

final class CfRunner
{
    private const VERSION = '1.0';
    private const ZONA_LOG = 'UTC';

    /** Interruptores que el deploy deja SIEMPRE en false (el manifiesto puede sumar más). */
    private const FLAGS_APAGADOS = ['RUKOZ_INTEGRACION', 'INTEGRACION_CATALOGO', 'INTEGRACION_STOCK', 'INTEGRACION_CHECKOUT', 'INTEGRACION_CHECKOUT_SIN_RESERVA', 'INTEGRACION_CONFIRMACION'];

    /** Funciones de PHP que el propio runner usa: si el hosting alguna vez deshabilita una, se frena antes de empezar. */
    private const FUNCIONES = ['rename', 'copy', 'unlink', 'mkdir', 'rmdir', 'scandir', 'file_put_contents', 'file_get_contents', 'fopen', 'flock', 'hash_file', 'hash_init', 'random_bytes', 'disk_free_space', 'chmod', 'touch', 'realpath', 'gzopen', 'gzwrite', 'gzgets', 'curl_init', 'curl_exec', 'tempnam', 'fileperms', 'json_encode'];

    private const EXTENSIONES = ['pdo_mysql', 'zip', 'curl', 'mbstring', 'openssl', 'json', 'zlib', 'fileinfo', 'tokenizer', 'ctype', 'dom', 'libxml', 'xml', 'iconv', 'filter', 'hash', 'session', 'pcre'];

    /** Rutas que hoy responden y tienen que seguir respondiendo igual (código e «Inertia»). */
    private const RUTAS_IGUALES = ['/', '/productos', '/contacto', '/servicios/chispas', '/login'];

    private string $base;
    private string $home;
    private string $laravel;
    private string $publico;
    private string $backups;
    private string $work;

    private string $disparoPath;
    private string $estadoPath;
    private string $logPath;
    private string $noncesPath;
    private string $lockPath;

    /** @var resource|null */
    private $lockFp = null;
    /** @var resource|null */
    private $backupLogFp = null;

    /** @var array<string, mixed> */
    private array $estado = [];
    /** @var array<string, mixed> */
    private array $disparo = [];
    /** @var array<string, mixed> */
    private array $manifest = [];
    /** @var array<string, string> */
    private array $env = [];

    private ?string $pasoActual = null;
    private float $pasoT0 = 0.0;
    private string $backup = '';
    private string $urlBase = '';
    private string $secreto = '';
    private string $jar = '';
    private int $erroresLogAntes = 0;
    /** @var array<string, array<string, mixed>> */
    private array $httpAntes = [];
    private string $hoyIso = '';
    private bool $laravelCargado = false;

    public function __construct()
    {
        date_default_timezone_set(self::ZONA_LOG);
        $this->base = rtrim(str_replace('\\', '/', __DIR__), '/');
        $this->home = dirname($this->base);
        $this->laravel = $this->home.'/laravel';
        $this->publico = $this->home.'/public_html';
        $this->backups = $this->home.'/backups-cf';
        $this->work = $this->base.'/work';
        $this->disparoPath = $this->base.'/disparo.json';
        $this->estadoPath = $this->base.'/estado.json';
        $this->logPath = $this->base.'/runner.log';
        $this->noncesPath = $this->base.'/nonces-usados.txt';
        $this->lockPath = $this->base.'/runner.lock';
    }

    // ================================================================================================================
    // Arranque
    // ================================================================================================================

    public function ejecutar(): int
    {
        $estadoPrevio = $this->leerJson($this->estadoPath);
        $hayDisparo = is_file($this->disparoPath);
        $murio = ($estadoPrevio['estado'] ?? '') === 'ejecutando';

        if (! $hayDisparo && ! $murio) {
            return 0; // lo normal: nada que hacer
        }

        $this->lockFp = @fopen($this->lockPath, 'c');
        if ($this->lockFp === false || ! flock($this->lockFp, LOCK_EX | LOCK_NB)) {
            return 0; // otra corrida está trabajando
        }

        @set_time_limit(0);
        @ini_set('memory_limit', '1024M');
        @ignore_user_abort(true);
        umask(0022);
        set_error_handler(static function (int $nivel, string $msg, string $archivo, int $linea): bool {
            if (! (error_reporting() & $nivel) || in_array($nivel, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
                return false;
            }
            throw new ErrorException($msg, 0, $nivel, $archivo, $linea);
        });

        $this->estado = $estadoPrevio;
        if ($murio) {
            return $this->recuperarDeCaida();
        }

        return $this->procesarDisparo();
    }

    private function procesarDisparo(): int
    {
        $ensayoOk = $this->estado['ensayo_ok'] ?? null;
        $inicioLog = is_file($this->logPath) ? (int) filesize($this->logPath) : 0;
        $this->estado = ['runner' => self::VERSION, 'estado' => 'ejecutando', 'fase' => 'A', 'paso' => 'lectura del disparo', 'inicio' => gmdate('c'), 'pasos' => [], 'avisos' => [], 'resumen' => [], 'manual' => [], 'tocado' => [], 'log_desde' => $inicioLog];
        if ($ensayoOk) {
            $this->estado['ensayo_ok'] = $ensayoOk;
        }
        $this->rotarLog();

        try {
            $this->leerDisparo();
        } catch (CfFallo $e) {
            $this->log('Disparo rechazado: '.$e->getMessage(), 'ERROR');
            $this->estado['estado'] = 'rechazado';
            $this->estado['error'] = $e->getMessage();
            $this->estado['fin'] = gmdate('c');
            $this->guardarEstado();
            $this->borrarDisparo();

            return 1;
        }

        $modo = $this->disparo['modo'];
        $this->estado['modo'] = $modo;
        $this->estado['nonce'] = $this->disparo['nonce'];
        $this->estado['commit'] = $this->disparo['commit'];
        $this->log(str_repeat('=', 78));
        $this->log('runner '.self::VERSION.' · modo '.strtoupper($modo).' · commit '.substr($this->disparo['commit'], 0, 7).' · nonce '.substr($this->disparo['nonce'], 0, 8));
        $this->guardarEstado();

        $codigo = 1;
        try {
            if ($modo === 'deploy') {
                $this->exigirEnsayo();
            }
            $this->faseA();
            if ($modo === 'ensayo') {
                $this->arranqueDePrueba();
                $this->cerrarPaso();
                $this->estado['ensayo_ok'] = ['commit' => $this->disparo['commit'], 'zip_sha256' => $this->disparo['zip_sha256'], 'nonce' => $this->disparo['nonce'], 'at' => gmdate('c')];
                $this->finOk('ENSAYO OK: no se tocó nada del sitio.');
            } else {
                $this->faseB();
                $this->faseC();
                $this->faseD();
                $this->faseE();
                $this->faseF();
                $this->finOk('DEPLOY OK: '.substr($this->disparo['commit'], 0, 7).' publicado con la integración apagada.');
            }
            $codigo = 0;
        } catch (Throwable $e) {
            $this->manejarFalla($e);
        } finally {
            $this->cerrarPaso();
            $this->limpiar();
            $this->borrarDisparo();
            $this->guardarEstado();
            if ($this->backupLogFp) {
                fclose($this->backupLogFp);
                $this->backupLogFp = null;
            }
        }

        return $codigo;
    }

    private function finOk(string $mensaje): void
    {
        $this->cerrarPaso();
        $this->estado['estado'] = 'ok';
        $this->estado['secreto'] = null;
        $this->estado['fase'] = 'fin';
        $this->estado['fin'] = gmdate('c');
        $this->estado['mensaje'] = $mensaje;
        $this->log($mensaje);
        $this->guardarEstado();
    }

    private function manejarFalla(Throwable $e): void
    {
        $this->cerrarPaso();
        $detalle = $e->getMessage().' ('.basename($e->getFile()).':'.$e->getLine().')';
        $this->estado['pasos'][] = ['n' => (string) ($this->estado['paso'] ?? '?'), 'seg' => 0, 'ok' => false];
        $this->estado['error'] = $detalle;
        $this->log('FALLÓ en «'.($this->estado['paso'] ?? '?').'»: '.$detalle, 'ERROR');
        if (! $e instanceof CfFallo) {
            $this->log($e->getTraceAsString(), 'ERROR');
        }

        if ($this->tocado()) {
            $this->log('--- VUELTA ATRÁS ---', 'AVISO');
            $ok = $this->vueltaAtras();
            $this->estado['estado'] = $ok ? 'vuelta_atras_ok' : 'vuelta_atras_fallo';
            $this->estado['mensaje'] = $ok
                ? 'El deploy falló y se volvió atrás: el sitio quedó arriba con el código y la base de antes.'
                : 'El deploy falló y la VUELTA ATRÁS NO TERMINÓ: el sitio puede estar en mantenimiento. Backup: '.$this->backup;
        } else {
            $this->estado['estado'] = 'fallo';
            $this->estado['mensaje'] = 'Se detuvo antes de tocar el sitio: no se modificó nada.';
        }
        $this->estado['fase'] = 'fin';
        $this->estado['fin'] = gmdate('c');
        $this->log((string) $this->estado['mensaje'], $this->estado['estado'] === 'vuelta_atras_fallo' ? 'ERROR' : 'AVISO');
        $this->guardarEstado();
    }

    /** El proceso anterior murió (timeout del cron, reinicio): si había tocado el sitio, se vuelve atrás. */
    private function recuperarDeCaida(): int
    {
        $this->log(str_repeat('=', 78));
        $this->log('Se encontró una corrida anterior sin terminar (el proceso murió). Fase: '.($this->estado['fase'] ?? '?').' · paso: '.($this->estado['paso'] ?? '?'), 'AVISO');
        $this->backup = (string) ($this->estado['backup'] ?? '');
        $this->secreto = (string) ($this->estado['secreto'] ?? '');
        $this->disparo = (array) ($this->estado['disparo'] ?? []);
        $this->estado['recuperada_at'] = gmdate('c');
        // El disparo de la corrida que murió sigue ahí (se borra en el finally, que no llegó a correr): no se vuelve a ejecutar.
        $viejo = $this->leerJson($this->disparoPath);
        if ($viejo !== [] && ($viejo['nonce'] ?? '') === ($this->estado['nonce'] ?? null)) {
            $this->borrarDisparo();
        }

        try {
            if (! $this->tocado()) {
                $this->estado['estado'] = 'fallo';
                $this->estado['mensaje'] = 'La corrida anterior murió antes de tocar el sitio: no se modificó nada.';
                $this->log((string) $this->estado['mensaje']);
                $codigo = 1;
            } else {
                $this->env = $this->parseEnv($this->backup.'/env.bak');
                $this->urlBase = rtrim((string) ($this->env['APP_URL'] ?? ''), '/');
                $ok = $this->vueltaAtras();
                $this->estado['estado'] = $ok ? 'vuelta_atras_ok' : 'vuelta_atras_fallo';
                $this->estado['mensaje'] = $ok ? 'La corrida anterior murió a mitad del deploy; se volvió atrás y el sitio quedó arriba.' : 'La corrida anterior murió y la vuelta atrás NO terminó. Backup: '.$this->backup;
                $codigo = 1;
            }
        } catch (Throwable $e) {
            $this->estado['estado'] = 'vuelta_atras_fallo';
            $this->estado['mensaje'] = 'Error al recuperar de la caída: '.$e->getMessage();
            $this->log((string) $this->estado['mensaje'], 'ERROR');
            $codigo = 1;
        }
        $this->estado['fin'] = gmdate('c');
        $this->estado['fase'] = 'fin';
        $this->guardarEstado();
        // Un disparo nuevo que haya quedado esperando se atiende en la próxima corrida del cron.

        return $codigo;
    }

    private function tocado(): bool
    {
        return array_filter((array) ($this->estado['tocado'] ?? [])) !== [];
    }

    private function marcar(string $que, mixed $valor = true): void
    {
        $this->estado['tocado'][$que] = $valor;
        $this->guardarEstado();
    }

    // ================================================================================================================
    // Disparo
    // ================================================================================================================

    private function leerDisparo(): void
    {
        $d = $this->leerJson($this->disparoPath);
        if ($d === []) {
            throw new CfFallo('disparo.json no se pudo leer o está vacío.');
        }
        foreach (['modo', 'commit', 'zip', 'zip_sha256', 'nonce', 'creado'] as $k) {
            if (! isset($d[$k]) || ! is_string($d[$k]) || $d[$k] === '') {
                throw new CfFallo("disparo.json: falta «{$k}».");
            }
        }
        if (! in_array($d['modo'], ['ensayo', 'deploy'], true)) {
            throw new CfFallo('disparo.json: modo «'.$d['modo'].'» desconocido (ensayo|deploy).');
        }
        if (! preg_match('/^[0-9a-f]{40}$/', $d['commit']) || ! preg_match('/^[0-9a-f]{64}$/', $d['zip_sha256'])) {
            throw new CfFallo('disparo.json: commit o sha256 con formato inválido.');
        }
        if (! preg_match('/^[A-Za-z0-9._-]{1,64}\.zip$/', $d['zip'])) {
            throw new CfFallo('disparo.json: nombre de paquete inválido.');
        }
        if (! preg_match('/^[A-Za-z0-9-]{16,64}$/', $d['nonce'])) {
            throw new CfFallo('disparo.json: nonce inválido.');
        }
        $creado = strtotime($d['creado']);
        if ($creado === false || abs(time() - $creado) > 3 * 3600) {
            throw new CfFallo('El disparo es de hace más de 3 horas (o tiene la fecha en el futuro): se descarta. Volvé a lanzar publicar.ps1.');
        }
        $usados = is_file($this->noncesPath) ? preg_split('/\R/', (string) file_get_contents($this->noncesPath)) : [];
        if (in_array($d['nonce'], $usados ?: [], true)) {
            throw new CfFallo('Este disparo ya se ejecutó antes (nonce repetido): se descarta.');
        }
        file_put_contents($this->noncesPath, $d['nonce']."\n", FILE_APPEND);
        $d['htaccess'] = ($d['htaccess'] ?? 'no') === 'merge' ? 'merge' : 'no';
        $this->disparo = $d;
        $this->estado['disparo'] = $d;
    }

    private function borrarDisparo(): void
    {
        if (is_file($this->disparoPath)) {
            @unlink($this->disparoPath);
        }
    }

    private function exigirEnsayo(): void
    {
        $e = $this->estado['ensayo_ok'] ?? null;
        $vigente = is_array($e) && ($e['commit'] ?? '') === $this->disparo['commit'] && ($e['zip_sha256'] ?? '') === $this->disparo['zip_sha256']
            && isset($e['at']) && (time() - (int) strtotime((string) $e['at'])) < 24 * 3600;
        if (! $vigente) {
            throw new CfFallo('No hay un ENSAYO exitoso (de las últimas 24 h) de este mismo paquete. Corré primero publicar.ps1 -Ensayo.');
        }
        $this->ok('Hay un ensayo exitoso de este paquete ('.$e['at'].')');
    }

    // ================================================================================================================
    // Fase A — verificaciones (también las hace el ensayo)
    // ================================================================================================================

    private function faseA(): void
    {
        $this->estado['fase'] = 'A';
        $this->paso('A1 · Relevamiento de la CLI');
        $this->relevarCli();

        $this->paso('A2 · Paquete: tamaño, sha256 y manifiesto');
        $this->verificarPaquete();

        $this->paso('A3 · Paquete: extracción verificada archivo por archivo');
        $this->extraerPaquete();
        $this->verificarArbolNuevo();

        $this->paso('A4 · .env y estructura actual del hosting');
        $this->verificarEstructura();

        $this->paso('A5 · Base de datos: conexión, migraciones y volcado de prueba');
        $this->verificarBase();

        $this->paso('A6 · Espacio libre y renombres');
        $this->verificarEspacio();

        $this->paso('A7 · Cómo responde la tienda hoy');
        $this->medirTienda();
    }

    private function relevarCli(): void
    {
        $dis = array_values(array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions')))));
        $info = [
            'php' => PHP_VERSION, 'sapi' => PHP_SAPI, 'so' => PHP_OS_FAMILY, 'usuario' => get_current_user(),
            'ini' => php_ini_loaded_file() ?: '(ninguno)', 'disable_functions' => $dis,
            'open_basedir' => (string) ini_get('open_basedir'), 'max_execution_time' => (string) ini_get('max_execution_time'),
            'memory_limit' => (string) ini_get('memory_limit'), 'allow_url_fopen' => (string) ini_get('allow_url_fopen'),
            'tmp' => sys_get_temp_dir(), 'extensiones' => count(get_loaded_extensions()),
        ];
        $this->estado['cli'] = $info;
        $this->log('PHP '.PHP_VERSION.' ('.PHP_SAPI.', '.PHP_OS_FAMILY.') · usuario '.$info['usuario'].' · ini '.$info['ini']);
        $this->log('disable_functions: '.($dis ? implode(' ', $dis) : '(ninguna)'));
        $this->log('open_basedir: '.($info['open_basedir'] ?: '(sin límite)').' · max_execution_time '.$info['max_execution_time'].' · memory_limit '.$info['memory_limit']);

        if (PHP_VERSION_ID < 80200) {
            throw new CfFallo('El PHP de la CLI es '.PHP_VERSION.' y se necesita 8.2 o más. Usá /opt/php8-2/bin/php en el cron.');
        }
        $faltanFn = array_values(array_filter(self::FUNCIONES, static fn (string $f): bool => ! function_exists($f)));
        if ($faltanFn) {
            throw new CfFallo('Faltan (o están deshabilitadas) funciones de PHP que usa el runner: '.implode(', ', $faltanFn));
        }
        $faltanExt = array_values(array_filter(self::EXTENSIONES, static fn (string $e): bool => ! extension_loaded($e)));
        if ($faltanExt) {
            throw new CfFallo('Faltan extensiones de PHP en la CLI: '.implode(', ', $faltanExt));
        }
        if ($info['open_basedir'] !== '') {
            $permitidos = array_filter(array_map(static fn (string $p): string => rtrim(str_replace('\\', '/', $p), '/'), explode(PATH_SEPARATOR, $info['open_basedir'])));
            foreach ([$this->home, $this->base] as $ruta) {
                $dentro = false;
                foreach ($permitidos as $p) {
                    if ($ruta === $p || str_starts_with($ruta.'/', $p.'/')) {
                        $dentro = true;
                    }
                }
                if (! $dentro) {
                    throw new CfFallo("open_basedir de la CLI no incluye {$ruta}: el runner no podría trabajar.");
                }
            }
        }
        if (! is_dir($this->work) && ! @mkdir($this->work, 0700, true)) {
            throw new CfFallo('No puedo crear '.$this->work.'.');
        }
        $this->ok('CLI con PHP '.PHP_VERSION.', las '.count(self::FUNCIONES).' funciones y las '.count(self::EXTENSIONES).' extensiones que usa el runner');
    }

    private function verificarPaquete(): void
    {
        $zipPath = $this->base.'/'.$this->disparo['zip'];
        if (! is_file($zipPath)) {
            throw new CfFallo('No encuentro '.$zipPath.' (¿terminó la subida por FTP?).');
        }
        $tam = (int) filesize($zipPath);
        if (isset($this->disparo['zip_bytes']) && (int) $this->disparo['zip_bytes'] !== $tam) {
            throw new CfFallo("El paquete pesa {$tam} bytes y el disparo dice {$this->disparo['zip_bytes']}: la subida quedó incompleta.");
        }
        $sha = hash_file('sha256', $zipPath);
        if ($sha !== $this->disparo['zip_sha256']) {
            throw new CfFallo('El sha256 del paquete NO coincide ('.substr((string) $sha, 0, 12).'… en el servidor, '.substr($this->disparo['zip_sha256'], 0, 12).'… en el disparo). Volvé a subirlo.');
        }
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            throw new CfFallo('El paquete no abre como zip.');
        }
        $json = $zip->getFromName('manifest.json');
        if ($json === false) {
            $zip->close();
            throw new CfFallo('El paquete no trae manifest.json.');
        }
        $m = json_decode($json, true);
        if (! is_array($m) || ($m['esquema'] ?? 0) !== 1) {
            $zip->close();
            throw new CfFallo('manifest.json inválido o de otro esquema.');
        }
        if (($m['commit'] ?? '') !== $this->disparo['commit']) {
            $zip->close();
            throw new CfFallo('El paquete es del commit '.substr((string) ($m['commit'] ?? ''), 0, 7).' y el disparo pide '.substr($this->disparo['commit'], 0, 7).'.');
        }
        $this->manifest = $m;

        // Cada entrada del zip tiene que estar declarada en el manifiesto y respetar las listas de lo que NUNCA se toca.
        $declaradas = [];
        foreach ($m['archivos'] as $a) {
            $declaradas[(string) $a['r']] = $a;
        }
        $vistas = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $n = (string) $zip->getNameIndex($i);
            if ($n === 'manifest.json' || str_ends_with($n, '/')) {
                continue;
            }
            $this->exigirNombrePermitido($n);
            if (! isset($declaradas[$n])) {
                $zip->close();
                throw new CfFallo("El zip trae «{$n}», que el manifiesto no declara.");
            }
            $vistas[$n] = true;
        }
        $zip->close();
        foreach (array_keys($declaradas) as $n) {
            $this->exigirNombrePermitido($n);
            if (! isset($vistas[$n])) {
                throw new CfFallo("El manifiesto declara «{$n}» pero el zip no lo trae.");
            }
        }
        $this->ok('Paquete '.round($tam / 1048576, 1).' MB · sha256 '.substr($sha, 0, 16).'… == disparo · commit '.substr($m['commit'], 0, 7).' · '.count($declaradas).' archivos declarados');
    }

    /** Lo que el paquete NUNCA puede contener: .env, storage, subidas del admin, PHP en public_html, index.php, cgi-bin, rutas raras. */
    private function exigirNombrePermitido(string $n): void
    {
        $malo = static fn (string $porque) => new CfFallo("Entrada prohibida en el paquete: «{$n}» ({$porque}).");
        if ($n === '' || str_contains($n, "\0") || str_contains($n, '\\') || str_starts_with($n, '/') || preg_match('#(^|/)\.\.(/|$)#', $n) || str_contains($n, '//') || preg_match('#^[A-Za-z]:#', $n)) {
            throw $malo('ruta insegura');
        }
        foreach (explode('/', $n) as $seg) {
            if (str_starts_with(strtolower($seg), '.env')) {
                throw $malo('.env');
            }
        }
        if (str_starts_with($n, 'laravel/')) {
            if (str_starts_with($n, 'laravel/storage/') || str_starts_with($n, 'laravel/public/')) {
                throw $malo('storage/ y public/ de laravel/ se conservan del servidor');
            }
            if (preg_match('#^laravel/\.git#', $n)) {
                throw $malo('git');
            }

            return;
        }
        if (str_starts_with($n, 'public_html/')) {
            $rel = substr($n, strlen('public_html/'));
            if (preg_match('#\.(php\d?|phtml|phar|pht|phps|htaccess|htpasswd|sh|cgi|pl|py)$#i', $rel) || str_starts_with($rel, '.')) {
                throw $malo('código ejecutable o archivo oculto en public_html');
            }
            if (preg_match('#^(cgi-bin|docs|images/products|tmp|logos|etc|mail|storagedir|tmpsite)(/|$)#', $rel)) {
                throw $malo('carpeta del hosting o de subidas del admin');
            }
            if (str_starts_with($rel, 'build/')) {
                return;
            }
            if (in_array($rel, (array) ($this->manifest['estaticos'] ?? []), true)) {
                return;
            }
            throw $malo('no está en la lista de estáticos del manifiesto');
        }
        throw $malo('fuera de laravel/ y public_html/');
    }

    private function extraerPaquete(): void
    {
        $this->rmtree($this->work.'/pkg', true);
        @mkdir($this->work.'/pkg', 0700, true);
        $destL = $this->work.'/laravel.nuevo';
        $destP = $this->work.'/public_html.nuevo';
        $this->rmtree($destL, true);
        $this->rmtree($destP, true);
        $zip = new ZipArchive();
        $zip->open($this->base.'/'.$this->disparo['zip'], ZipArchive::RDONLY);
        $total = 0;
        $bytes = 0;
        foreach ($this->manifest['archivos'] as $a) {
            $r = (string) $a['r'];
            $destino = str_starts_with($r, 'laravel/') ? $destL.'/'.substr($r, 8) : $destP.'/'.substr($r, 12);
            $dir = dirname($destino);
            if (! is_dir($dir) && ! @mkdir($dir, 0755, true)) {
                throw new CfFallo("No pude crear {$dir}.");
            }
            $in = $zip->getStream($r);
            if ($in === false) {
                throw new CfFallo("No pude leer «{$r}» del zip.");
            }
            $out = fopen($destino, 'wb');
            $ctx = hash_init('sha256');
            $n = 0;
            while (! feof($in)) {
                $chunk = fread($in, 1048576);
                if ($chunk === false || $chunk === '') {
                    break;
                }
                fwrite($out, $chunk);
                hash_update($ctx, $chunk);
                $n += strlen($chunk);
            }
            fclose($in);
            fclose($out);
            if (hash_final($ctx) !== $a['s'] || $n !== (int) $a['b']) {
                throw new CfFallo("«{$r}» no coincide con el manifiesto (sha256 o tamaño). El paquete está dañado.");
            }
            @chmod($destino, 0644);
            $total++;
            $bytes += $n;
        }
        $zip->close();
        $this->estado['extraido_bytes'] = $bytes;
        $this->ok("{$total} archivos extraídos y verificados (sha256 y tamaño), ".round($bytes / 1048576, 1).' MB');
    }

    private function verificarArbolNuevo(): void
    {
        $n = $this->work.'/laravel.nuevo';
        foreach (['vendor/autoload.php', 'vendor/composer/platform_check.php', 'vendor/composer/installed.json', 'bootstrap/app.php', 'artisan', 'routes/web.php', 'composer.lock', 'vendor/rukoz/core-client/composer.json'] as $f) {
            if (! is_file($n.'/'.$f)) {
                throw new CfFallo("El paquete no trae laravel/{$f}.");
            }
        }
        if (is_dir($n.'/vendor/phpunit')) {
            throw new CfFallo('El vendor del paquete trae dependencias de desarrollo (phpunit).');
        }
        if (! is_file($this->work.'/public_html.nuevo/build/manifest.json')) {
            throw new CfFallo('El paquete no trae public_html/build/manifest.json.');
        }
        $bm = hash_file('sha256', $this->work.'/public_html.nuevo/build/manifest.json');
        if (isset($this->manifest['build_manifest_sha256']) && $bm !== $this->manifest['build_manifest_sha256']) {
            throw new CfFallo('El build/manifest.json no es el que declara el manifiesto.');
        }
        // Lo que exige el vendor (platform_check.php) se comprueba acá, ANTES de cargarlo: si falta algo, ese archivo mata el proceso.
        $faltan = array_values(array_filter((array) ($this->manifest['extensiones'] ?? []), static fn (string $e): bool => ! extension_loaded($e)));
        if ($faltan) {
            throw new CfFallo('El composer.lock exige extensiones que la CLI no tiene: '.implode(', ', $faltan));
        }
        $pc = (string) file_get_contents($n.'/vendor/composer/platform_check.php');
        if (preg_match('/PHP_VERSION_ID >= (\d+)/', $pc, $mm) && PHP_VERSION_ID < (int) $mm[1]) {
            throw new CfFallo('El vendor exige PHP '.$mm[1].' y la CLI es '.PHP_VERSION.'.');
        }
        $this->ok('Árbol nuevo completo: autoload, platform_check, rukoz/core-client, build (manifest sha256 '.substr($bm, 0, 12).'…), sin dependencias de desarrollo');
    }

    private function verificarEstructura(): void
    {
        $envPath = $this->laravel.'/.env';
        foreach ([$this->laravel.'/vendor/autoload.php' => 'vendor del sitio actual', $envPath => '.env', $this->laravel.'/storage/framework' => 'storage/framework', $this->publico.'/index.php' => 'public_html/index.php'] as $ruta => $que) {
            if (! file_exists($ruta)) {
                throw new CfFallo("No encuentro {$que} ({$ruta}): la estructura del hosting no es la esperada.");
            }
        }
        $this->env = $this->parseEnv($envPath);
        $e = $this->env;
        if (($e['APP_ENV'] ?? '') !== 'production') {
            throw new CfFallo('APP_ENV es «'.($e['APP_ENV'] ?? '').'» (se espera production).');
        }
        if (strtolower($e['APP_DEBUG'] ?? '') !== 'false') {
            throw new CfFallo('APP_DEBUG es «'.($e['APP_DEBUG'] ?? '').'» (se espera false).');
        }
        if (($e['DB_CONNECTION'] ?? '') !== 'mysql') {
            throw new CfFallo('DB_CONNECTION es «'.($e['DB_CONNECTION'] ?? '').'» (se espera mysql).');
        }
        foreach (['DB_HOST', 'DB_DATABASE', 'DB_USERNAME'] as $k) {
            if (($e[$k] ?? '') === '') {
                throw new CfFallo("Falta {$k} en el .env.");
            }
        }
        $md = $e['APP_MAINTENANCE_DRIVER'] ?? 'file';
        if ($md !== 'file') {
            throw new CfFallo("APP_MAINTENANCE_DRIVER es «{$md}»: el runner sólo maneja el modo mantenimiento por archivo.");
        }
        $this->urlBase = rtrim($e['APP_URL'] ?? '', '/');
        $permitirHttp = getenv('CF_RUNNER_PRUEBA') !== false;
        if (! preg_match($permitirHttp ? '#^https?://#' : '#^https://#', $this->urlBase)) {
            throw new CfFallo('APP_URL («'.$this->urlBase.'») no es https.');
        }
        $this->log('Rutas de subidas según el .env: PRODUCT_IMAGES_PATH='.($e['PRODUCT_IMAGES_PATH'] ?? '(no está)').' · VITE_PRODUCT_IMAGES_PATH='.($e['VITE_PRODUCT_IMAGES_PATH'] ?? '(no está)').' · DOCUMENTS_PDF_PATH='.($e['DOCUMENTS_PDF_PATH'] ?? '(no está)'));
        foreach (array_values(array_unique(array_merge(self::FLAGS_APAGADOS, (array) ($this->manifest['flags_apagados'] ?? [])))) as $f) {
            $v = $e[$f] ?? null;
            $this->log("  {$f} = ".($v ?? '(no está: apagado por defecto)'));
            if ($v !== null && strtolower($v) === 'true') {
                $this->aviso("{$f} está en true en el .env: el deploy lo va a APAGAR.");
            }
        }
        $perm = fileperms($envPath) & 0777;
        $this->estado['env_mode'] = $perm;
        $this->log('.env con permisos '.decoct($perm));

        $idx = (string) file_get_contents($this->publico.'/index.php');
        $this->estado['index_sha256'] = hash('sha256', $idx);
        if (! str_contains($idx, 'bootstrap/app.php') || ! preg_match('#laravel#', $idx)) {
            throw new CfFallo('public_html/index.php no parece apuntar a ../laravel (no menciona laravel ni bootstrap/app.php): hay que revisarlo antes de desplegar.');
        }
        foreach (preg_split('/\R/', $idx) ?: [] as $linea) {
            if (preg_match('#require|usePublicPath|maintenance|bootstrap/app|autoload#', $linea) && ! str_starts_with(ltrim($linea), '//')) {
                $this->log('  index.php: '.trim($linea));
            }
        }
        $usaPublic = str_contains($idx, 'usePublicPath');
        $this->log('public_html/index.php '.($usaPublic ? 'FIJA usePublicPath: public_path() apunta a public_html' : 'no fija usePublicPath: public_path() es laravel/public (las subidas salen por PRODUCT_IMAGES_PATH relativo)'));
        foreach ([$this->publico.'/hot' => 'public_html/hot', $this->laravel.'/public/hot' => 'laravel/public/hot'] as $ruta => $que) {
            if (is_file($ruta)) {
                $this->aviso("Existe {$que}: se saca en el deploy ("
                    .($ruta === $this->publico.'/hot' && $usaPublic ? 'con usePublicPath Vite lo tomaría por servidor de desarrollo y la tienda cargaría los assets de localhost' : 'es un resto de «npm run dev»').').');
            }
        }
        foreach ([$this->home, $this->publico, $this->laravel, $this->laravel.'/storage', $this->laravel.'/bootstrap'] as $dir) {
            if (! is_writable($dir)) {
                throw new CfFallo("{$dir} no es escribible por el usuario del cron.");
            }
        }
        $this->ok('Estructura OK · APP_ENV=production · APP_DEBUG=false · APP_URL='.$this->urlBase);
    }

    private function pdo(?array $env = null): PDO
    {
        $e = $env ?? $this->env;
        $dsn = 'mysql:host='.$e['DB_HOST'].';port='.($e['DB_PORT'] ?? '3306').';dbname='.$e['DB_DATABASE'].';charset=utf8mb4';
        if (! empty($e['DB_SOCKET'])) {
            $dsn = 'mysql:unix_socket='.$e['DB_SOCKET'].';dbname='.$e['DB_DATABASE'].';charset=utf8mb4';
        }

        return new PDO($dsn, $e['DB_USERNAME'], $e['DB_PASSWORD'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_STRINGIFY_FETCHES => true,
            PDO::ATTR_EMULATE_PREPARES => true,
        ]);
    }

    private function verificarBase(): void
    {
        try {
            $pdo = $this->pdo();
        } catch (Throwable $e) {
            throw new CfFallo('No puedo entrar a la base con las credenciales del .env: '.preg_replace('/password=[^ ]+/i', 'password=***', $e->getMessage()));
        }
        $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
        $tablas = $this->listarTablas($pdo);
        $this->ok('Base '.$this->env['DB_DATABASE'].' en MySQL/MariaDB '.$version.' · '.count($tablas['tablas']).' tablas, '.count($tablas['vistas']).' vistas');
        foreach (['trigger' => 'SHOW TRIGGERS', 'rutina' => "SHOW PROCEDURE STATUS WHERE Db = DATABASE()", 'función' => "SHOW FUNCTION STATUS WHERE Db = DATABASE()", 'evento' => 'SHOW EVENTS'] as $que => $sql) {
            $n = count($pdo->query($sql)->fetchAll());
            if ($n > 0 && $que !== 'trigger') {
                $this->aviso("La base tiene {$n} {$que}(s): el volcado del runner NO las incluye (los triggers sí).");
            }
        }
        $aplicadas = $this->migracionesAplicadas($pdo);
        $this->log('Migraciones aplicadas hoy: '.count($aplicadas));
        $pend = $this->migracionesPendientes($aplicadas);
        $esperadas = (array) ($this->manifest['migraciones_esperadas'] ?? []);
        $this->estado['migraciones_pendientes'] = $pend;
        $this->log('Migraciones pendientes ('.count($pend).'):');
        foreach ($pend as $m) {
            $this->log('    '.$m);
        }
        $raras = array_values(array_diff($pend, $esperadas));
        if ($raras) {
            throw new CfFallo('Hay migraciones pendientes que el manifiesto no espera (¿la base está atrasada respecto de produccion-pre-integracion?): '.implode(', ', $raras));
        }
        $faltan = array_values(array_diff($esperadas, $pend));
        if ($faltan) {
            $this->aviso('Ya estaban aplicadas (o no existen) '.count($faltan).' de las migraciones esperadas: '.implode(', ', $faltan));
        }
        foreach (['product_offers', 'discount_codes'] as $t) {
            if (in_array($t, $tablas['tablas'], true)) {
                $this->log("  {$t}: ".$pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn().' filas');
            }
        }
        // Volcado de prueba (a /tmp si se puede, si no a work/): mide tiempo y tamaño y prueba que el restaurador lo lee.
        $tmp = sys_get_temp_dir();
        $destino = (is_writable($tmp) && $this->dentroDeBasedir($tmp) ? $tmp : $this->work).'/cf-dump-prueba-'.getmypid().'.sql.gz';
        $t0 = microtime(true);
        $r = $this->volcarBase($pdo, $destino);
        $seg = round(microtime(true) - $t0, 1);
        $this->estado['dump_prueba'] = ['bytes' => filesize($destino), 'seg' => $seg, 'tablas' => count($r['filas'])];
        $this->verificarDump($destino, $r);
        @unlink($destino);
        $this->ok('Volcado de prueba: '.count($r['filas']).' tablas, '.array_sum($r['filas']).' filas, '.round(((int) $this->estado['dump_prueba']['bytes']) / 1024).' KB comprimido en '.$seg.' s (borrado)');
    }

    private function verificarEspacio(): void
    {
        $libre = (int) @disk_free_space($this->home);
        $extraido = (int) ($this->estado['extraido_bytes'] ?? 0);
        $codigoActual = $this->tamanoArbol($this->laravel, ['vendor', 'storage/logs', 'storage/framework/cache', 'storage/framework/views', 'storage/framework/sessions']);
        $dump = (int) ($this->estado['dump_prueba']['bytes'] ?? 0);
        $necesario = (int) (($extraido + $codigoActual + 4 * $dump + $this->tamanoArchivo($this->base.'/'.$this->disparo['zip'])) * 1.5) + 200 * 1048576;
        $this->log('Espacio libre '.round($libre / 1048576).' MB · hace falta ≈ '.round($necesario / 1048576).' MB (extraído '.round($extraido / 1048576).' MB, código actual sin vendor '.round($codigoActual / 1048576).' MB)');
        if ($libre > 0 && $libre < $necesario) {
            throw new CfFallo('No alcanza el espacio libre en la cuenta.');
        }
        // El cambio de carpetas es un rename(): tiene que andar entre work/ y la raíz de la cuenta (mismo filesystem).
        $a = $this->work.'/prueba-rename';
        $b = $this->home.'/.cf-prueba-rename';
        $this->rmtree($a, true);
        $this->rmtree($b, true);
        @mkdir($a.'/x', 0700, true);
        file_put_contents($a.'/x/f', 'x');
        if (! @rename($a, $b) || ! is_file($b.'/x/f') || ! @rename($b, $a)) {
            throw new CfFallo('rename() entre work/ y la raíz de la cuenta no funciona: no se puede cambiar el código por renombre.');
        }
        $this->rmtree($a, true);
        $this->ok('rename() de carpetas entre deploy-cf/ y la raíz de la cuenta funciona');
    }

    private function medirTienda(): void
    {
        $this->jar = $this->work.'/cookies.txt';
        @unlink($this->jar);
        if (is_file($this->laravel.'/storage/framework/down')) {
            throw new CfFallo('El sitio YA está en mantenimiento (storage/framework/down existe). Resolvelo antes de desplegar.');
        }
        foreach (self::RUTAS_IGUALES as $r) {
            $h = $this->http($r, ['jar' => false]);
            $this->httpAntes[$r] = $h;
            $this->log(sprintf('  %-20s →  %s | %s | %s', $r, $h['code'], $h['titulo'], $h['inertia'] ? 'inertia' : '-'));
        }
        foreach (['/', '/productos'] as $r) {
            if ($this->httpAntes[$r]['code'] !== 200) {
                throw new CfFallo("{$r} responde {$this->httpAntes[$r]['code']} ANTES de tocar nada ("
                    .($this->httpAntes[$r]['error'] ?: 'sin error de red').'): no se despliega sobre una tienda que ya anda mal, o este servidor no alcanza su propia URL.');
            }
        }
        foreach (['/', '/productos'] as $r) {
            if (! $this->httpAntes[$r]['inertia'] || $this->httpAntes[$r]['titulo'] === '') {
                throw new CfFallo("{$r} responde 200 pero no parece la tienda (sin página de Inertia ni título): no hay con qué comparar. ¿APP_URL apunta a otro sitio?");
            }
        }
        $this->erroresLogAntes = $this->contarErroresLog();
        $this->ok('Medición lista (errores en laravel.log hoy: '.$this->erroresLogAntes.')');
    }

    // ================================================================================================================
    // Ensayo: arranque del Laravel NUEVO en una carpeta temporal (último paso: carga Laravel en este proceso)
    // ================================================================================================================

    private function arranqueDePrueba(): void
    {
        $this->paso('A8 · Arranque de prueba del Laravel nuevo (carpeta temporal, sin tocar el sitio)');
        $t = $this->work.'/laravel.nuevo';
        // .env de prueba: el de producción con todo apagado y SIN escrituras (cachés, sesiones y logs a memoria/carpeta temporal).
        $env = $this->env;
        foreach (array_merge(self::FLAGS_APAGADOS, (array) ($this->manifest['flags_apagados'] ?? [])) as $f) {
            $env[$f] = 'false';
        }
        $env = array_merge($env, ['CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'LOG_CHANNEL' => 'single', 'APP_MAINTENANCE_DRIVER' => 'file']);
        $lineas = '';
        foreach ($env as $k => $v) {
            $lineas .= $k.'='.$this->comillas($v)."\n";
        }
        file_put_contents($t.'/.env', $lineas);
        @chmod($t.'/.env', 0600);
        foreach (['app/public', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs'] as $d) {
            @mkdir($t.'/storage/'.$d, 0755, true);
        }
        @mkdir($t.'/bootstrap/cache', 0755, true);

        $kernel = $this->cargarLaravel($t);
        $salida = new Symfony\Component\Console\Output\BufferedOutput();
        $rutas = [];
        $codigo = $kernel->call('route:list', ['--json' => true], $salida);
        $rutas = json_decode($salida->fetch(), true);
        if ($codigo !== 0 || ! is_array($rutas)) {
            throw new CfFallo('route:list falló en el arranque de prueba (código '.$codigo.').');
        }
        $uris = array_map(static fn (array $r): string => '/'.ltrim((string) $r['uri'], '/'), $rutas);
        foreach (['/carrito', '/carrito/checkout', '/productos', '/robots.txt', '/sitemap.xml', '/admin'] as $u) {
            if (! in_array($u, $uris, true)) {
                throw new CfFallo("El Laravel nuevo no registra la ruta {$u}.");
            }
        }
        if (in_array('/rukoz/webhook', $uris, true)) {
            throw new CfFallo('Con la integración apagada el Laravel nuevo registra /rukoz/webhook (debería ser 404).');
        }
        $cfg = app('config');
        if ($cfg->get('rukoz.habilitada') !== false || $cfg->get('app.env') !== 'production') {
            throw new CfFallo('La config del Laravel nuevo no quedó con la integración apagada y APP_ENV=production.');
        }
        $this->ok(count($uris).' rutas registradas (incluye /carrito, /carrito/checkout, /robots.txt, /sitemap.xml; sin /rukoz/webhook) · rukoz.habilitada=false · APP_ENV=production');
        $salida = new Symfony\Component\Console\Output\BufferedOutput();
        $kernel->call('schedule:list', [], $salida);
        $this->log('schedule:list → '.trim(preg_replace('/\s+/', ' ', $salida->fetch())));
        $this->rmtree($t.'/storage', true);
        $this->rmtree($t.'/.env', true);
    }

    // ================================================================================================================
    // Fase B — backup de archivos (el sitio sigue arriba)
    // ================================================================================================================

    private function faseB(): void
    {
        $this->estado['fase'] = 'B';
        $this->paso('B1 · Backup de archivos en ~/backups-cf/');
        $this->backup = $this->backups.'/'.gmdate('Ymd-His');
        if (! is_dir($this->backups) && ! @mkdir($this->backups, 0700, true)) {
            throw new CfFallo('No puedo crear '.$this->backups);
        }
        @mkdir($this->backup, 0700, true);
        $this->estado['backup'] = $this->backup;
        $this->guardarEstado();
        $this->backupLogFp = fopen($this->backup.'/deploy.log', 'ab');

        copy($this->laravel.'/.env', $this->backup.'/env.bak');
        @chmod($this->backup.'/env.bak', 0600);
        foreach (['.htaccess', 'index.php', 'robots.txt', 'hot'] as $f) {
            if (is_file($this->publico.'/'.$f)) {
                $copia = $this->backup.'/public_html-'.ltrim($f, '.').'.copia';
                copy($this->publico.'/'.$f, $copia);
                @chmod($copia, 0600);
            }
        }
        $this->ok('Copias de .env, .htaccess, index.php (y robots.txt / hot si existen) en el backup. El .htaccess de hoy: '.$this->backup.'/public_html-htaccess.copia');

        $n = $this->zipArbol($this->laravel, 'laravel', $this->backup.'/laravel-codigo.zip', ['vendor', 'storage/logs', 'storage/framework/cache', 'storage/framework/views', 'storage/framework/sessions']);
        $this->ok("laravel-codigo.zip: {$n} archivos (sin vendor ni logs; el vendor viejo se conserva entero por renombre)");

        // Los archivos de public_html que se van a reemplazar o borrar.
        $reemplazados = ['build'];
        foreach ((array) ($this->manifest['estaticos'] ?? []) as $e) {
            $reemplazados[] = $e;
        }
        foreach ((array) ($this->manifest['eliminar_public_html'] ?? []) as $e) {
            $reemplazados[] = $e;
        }
        $n2 = $this->zipRutas($this->publico, 'public_html', $this->backup.'/public_html-reemplazados.zip', $reemplazados);
        $this->ok("public_html-reemplazados.zip: {$n2} archivos (build/, estáticos que se reemplazan y lo que se borra)");

        $this->estado['vigencias_antes'] = $this->vigencias($this->pdo());
        file_put_contents($this->backup.'/vigencias-antes.txt', $this->vigenciasTexto((array) $this->estado['vigencias_antes']));
        $this->gancho('despues_backup');
    }

    // ================================================================================================================
    // Fase C — mantenimiento, volcado de la base, cambio de código
    // ================================================================================================================

    private function faseC(): void
    {
        $this->estado['fase'] = 'C';
        $this->paso('C1 · Mantenimiento (503 prerenderizado, con clave de bypass para verificar)');
        $this->ponerMantenimiento();

        $this->paso('C2 · Backup de la base (con el sitio en mantenimiento: no se pierden pedidos)');
        $pdo = $this->pdo();
        $dump = $this->backup.'/base.sql.gz';
        $r = $this->volcarBase($pdo, $dump);
        $this->verificarDump($dump, $r);
        @chmod($dump, 0600);
        $this->estado['dump'] = ['filas' => $r['filas'], 'bytes' => filesize($dump)];
        $this->guardarEstado();
        $this->ok('base.sql.gz: '.count($r['filas']).' tablas, '.array_sum($r['filas']).' filas, '.round(filesize($dump) / 1024).' KB → '.$dump);
        $this->gancho('despues_mantenimiento');

        $this->paso('C3 · Código: laravel.nuevo → laravel (por renombre) · build y estáticos en public_html');
        $this->cambiarCodigo();
        $this->gancho('despues_codigo');

        $this->paso('C4 · .env: integraciones APAGADAS');
        $this->apagarIntegraciones();
    }

    private function ponerMantenimiento(): void
    {
        $this->secreto = bin2hex(random_bytes(12));
        $this->estado['secreto'] = $this->secreto;
        $this->marcar('mantenimiento');
        $this->escribirMantenimiento();
        usleep(1500000);

        $h = $this->http('/', ['jar' => false]);
        if ($h['code'] !== 503) {
            throw new CfFallo('El sitio debería dar 503 en mantenimiento y dio '.$h['code'].'. (¿public_html/index.php no pasa por storage/framework/maintenance.php?)');
        }
        $this->http('/'.$this->secreto, ['jar' => true]);
        $h = $this->http('/', ['jar' => true]);
        if ($h['code'] !== 200) {
            throw new CfFallo('Con la clave de bypass el sitio actual responde '.$h['code'].' (se esperaba 200): no se podrá verificar el deploy.');
        }
        // Los errores que haya metido el público hasta acá no cuentan como «nuevos».
        $this->erroresLogAntes = $this->contarErroresLog();
        $this->ok('Sitio en mantenimiento: 503 para el público y 200 con el bypass');
    }

    /** Equivale a `artisan down --render=… --secret=…` sin cargar Laravel: el 503 lo da el stub (PHP puro) aunque no haya vendor/ por un instante. */
    private function escribirMantenimiento(): void
    {
        $html = '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Volvemos enseguida</title>'
            .'<style>body{font-family:system-ui,sans-serif;background:#0b0b0f;color:#f4f4f5;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0;text-align:center}main{padding:2rem}h1{font-size:1.6rem;margin:0 0 .75rem}p{color:#a1a1aa;margin:0}</style></head>'
            .'<body><main><h1>Estamos actualizando la tienda</h1><p>Volvemos en unos minutos. ¡Gracias por la paciencia!</p></main></body></html>';
        $payload = ['except' => [], 'redirect' => null, 'retry' => 60, 'refresh' => null, 'secret' => $this->secreto, 'status' => 503, 'template' => $html];
        $stub = $this->laravel.'/vendor/laravel/framework/src/Illuminate/Foundation/Console/stubs/maintenance-mode.stub';
        if (! is_file($stub)) {
            $stub = $this->work.'/laravel.nuevo/vendor/laravel/framework/src/Illuminate/Foundation/Console/stubs/maintenance-mode.stub';
        }
        $dir = $this->laravel.'/storage/framework';
        file_put_contents($dir.'/down', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        file_put_contents($dir.'/maintenance.php', (string) file_get_contents($stub));
    }

    private function cambiarCodigo(): void
    {
        $n = $this->work.'/laravel.nuevo';
        $pn = $this->work.'/public_html.nuevo';
        $v = $this->backup.'/laravel.viejo';
        $this->marcar('codigo');

        // 1) Lo que no cambia con el deploy se MUEVE al árbol nuevo (rename, sin copias): storage/, .env y public/ (con las subidas que haya).
        @mkdir($n.'/bootstrap/cache', 0755, true);
        foreach (['storage', '.env', 'public'] as $pieza) {
            if (file_exists($this->laravel.'/'.$pieza)) {
                if (! @rename($this->laravel.'/'.$pieza, $n.'/'.$pieza)) {
                    throw new CfFallo("No pude mover laravel/{$pieza} al árbol nuevo.");
                }
            }
        }
        foreach (['app/public', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs'] as $d) {
            if (! is_dir($n.'/storage/'.$d)) {
                @mkdir($n.'/storage/'.$d, 0775, true);
            }
        }
        // 2) public/build: se aparta el viejo (para la vuelta atrás) y se pone el nuevo.
        @mkdir($n.'/public', 0755, true);
        if (is_dir($n.'/public/build') && ! @rename($n.'/public/build', $this->backup.'/laravel-public-build.viejo')) {
            throw new CfFallo('No pude apartar laravel/public/build.');
        }
        $this->copiarArbol($pn.'/build', $n.'/public/build');
        foreach ((array) ($this->manifest['eliminar_laravel_public'] ?? ['hot']) as $f) {
            if (is_file($n.'/public/'.$f)) {
                $this->copiaYBorra($n.'/public/'.$f, $this->backup.'/laravel-public-'.$f.'.copia');
            }
        }
        file_put_contents($n.'/.cf-version.json', json_encode(['commit' => $this->disparo['commit'], 'desplegado' => gmdate('c'), 'zip_sha256' => $this->disparo['zip_sha256']], JSON_PRETTY_PRINT));
        // 3) El intercambio: dos renombres seguidos (el 503 prerenderizado sigue respondiendo mientras tanto).
        if (! @rename($this->laravel, $v)) {
            throw new CfFallo('No pude apartar laravel/ al backup.');
        }
        if (! @rename($n, $this->laravel)) {
            @rename($v, $this->laravel);
            throw new CfFallo('No pude poner el código nuevo en laravel/ (se devolvió el viejo).');
        }
        if (! is_file($this->laravel.'/vendor/autoload.php') || ! is_file($this->laravel.'/.env') || ! is_dir($this->laravel.'/storage/framework')) {
            throw new CfFallo('Después del cambio laravel/ quedó incompleto.');
        }
        $this->ok('laravel/ ahora es el código '.substr($this->disparo['commit'], 0, 7).'; el anterior (con su vendor) está en '.$v);

        // 4) public_html: build (se aparta el viejo entero), estáticos y borrados.
        $this->marcar('build');
        if (is_dir($this->publico.'/build') && ! @rename($this->publico.'/build', $this->backup.'/public_html-build.viejo')) {
            throw new CfFallo('No pude apartar public_html/build.');
        }
        if (! @rename($pn.'/build', $this->publico.'/build')) {
            throw new CfFallo('No pude poner el build nuevo en public_html/build.');
        }
        $this->ok('public_html/build reemplazado ('.$this->contarArchivos($this->publico.'/build').' archivos)');

        $est = [];
        foreach ((array) ($this->manifest['estaticos'] ?? []) as $rel) {
            $dst = $this->publico.'/'.$rel;
            $src = $pn.'/'.$rel;
            $existia = is_file($dst);
            if ($existia) {
                $b = $this->backup.'/public_html-estaticos/'.$rel;
                @mkdir(dirname($b), 0700, true);
                copy($dst, $b);
            }
            @mkdir(dirname($dst), 0755, true);
            copy($src, $dst);
            @chmod($dst, 0644);
            $est[$rel] = $existia;
        }
        if ($est) {
            $this->marcar('estaticos', $est);
            $this->ok(count($est).' archivo(s) estático(s) actualizados en public_html');
        }
        foreach ((array) ($this->manifest['eliminar_public_html'] ?? []) as $rel) {
            if (is_file($this->publico.'/'.$rel)) {
                @unlink($this->publico.'/'.$rel);
                $this->marcar('borrados', array_merge((array) ($this->estado['tocado']['borrados'] ?? []), [$rel]));
                $this->ok("public_html/{$rel} borrado (copia en el backup)");
            }
        }
        if ($this->disparo['htaccess'] === 'merge') {
            $this->aplicarHtaccess();
        }
    }

    private function aplicarHtaccess(): void
    {
        $actual = $this->publico.'/.htaccess';
        $s2 = (string) ($this->manifest['htaccess_s2'] ?? '');
        $marca = '# --- Archivos que nunca se sirven';
        if ($s2 === '') {
            $this->aviso('El manifiesto no trae los bloques de seguridad del .htaccess: no se aplican.');

            return;
        }
        $texto = is_file($actual) ? (string) file_get_contents($actual) : '';
        if (str_contains($texto, $marca)) {
            $this->ok('El .htaccess de public_html ya trae los bloques de seguridad S.2');

            return;
        }
        $this->marcar('htaccess');
        file_put_contents($actual, "# Bloques de seguridad S.2 agregados por el deploy (commit ".substr($this->disparo['commit'], 0, 7).")\n".$s2."\n".$texto);
        // Si el servidor no acepta alguna directiva da 500: se prueba en seguida y, si falla, se vuelve al .htaccess de antes.
        $malo = $this->probarHtaccess();
        if ($malo !== null) {
            file_put_contents($actual, $texto);
            $this->marcar('htaccess', false);
            $this->aviso('El .htaccess con los bloques S.2 dio error ('.$malo.'): se dejó el de antes. La tienda sigue como estaba; revisá el .htaccess a mano (docs).');
            $this->estado['manual'][] = 'El .htaccess de public_html NO recibió los bloques de seguridad S.2 (el servidor los rechazó: '.$malo.').';

            return;
        }
        $this->ok('.htaccess de public_html con los bloques S.2: la tienda responde y /.env se rechaza');
    }

    private function probarHtaccess(): ?string
    {
        foreach (['/' => [200], '/chispas-frias-logo.png' => [200, 404], '/.env' => [403, 404]] as $r => $codigos) {
            $h = $this->http($r, ['jar' => true]);
            if (! in_array($h['code'], $codigos, true)) {
                return "{$r} → {$h['code']}";
            }
        }

        return null;
    }

    private function apagarIntegraciones(): void
    {
        $this->marcar('env');
        $flags = array_values(array_unique(array_merge(self::FLAGS_APAGADOS, (array) ($this->manifest['flags_apagados'] ?? []))));
        foreach ($flags as $f) {
            $this->setEnv($this->laravel.'/.env', $f, 'false');
        }
        $e = $this->parseEnv($this->laravel.'/.env');
        foreach ($flags as $f) {
            if (($e[$f] ?? '') !== 'false') {
                throw new CfFallo("{$f} no quedó en false en el .env.");
            }
        }
        if (($e['APP_ENV'] ?? '') !== 'production' || strtolower($e['APP_DEBUG'] ?? '') !== 'false') {
            throw new CfFallo('El .env perdió APP_ENV/APP_DEBUG.');
        }
        $this->env = $e;
        $this->ok('Apagadas en el .env: '.implode(', ', $flags));
    }

    // ================================================================================================================
    // Fase D — Laravel nuevo en este proceso
    // ================================================================================================================

    private function faseD(): void
    {
        $this->estado['fase'] = 'D';
        $this->paso('D1 · Laravel nuevo: arranque, package:discover');
        @array_map('unlink', glob($this->laravel.'/bootstrap/cache/*.php') ?: []);
        $kernel = $this->cargarLaravel($this->laravel);
        $this->artisan($kernel, 'package:discover');

        $this->paso('D2 · Migraciones (migrate --force)');
        $pdo = $this->pdo();
        $pend = $this->migracionesPendientes($this->migracionesAplicadas($pdo), $this->laravel);
        $this->log('Pendientes ('.count($pend).'): '.implode(', ', $pend));
        $raras = array_values(array_diff($pend, (array) ($this->manifest['migraciones_esperadas'] ?? [])));
        if ($raras) {
            throw new CfFallo('Pendientes inesperadas: '.implode(', ', $raras));
        }
        $this->marcar('base');
        $this->artisan($kernel, 'migrate', ['--force' => true]);
        $this->gancho('despues_migrar');
        $pdo = $this->pdo();
        $quedan = $this->migracionesPendientes($this->migracionesAplicadas($pdo), $this->laravel);
        if ($quedan) {
            throw new CfFallo('Después de migrar quedaron pendientes: '.implode(', ', $quedan));
        }
        $this->verificarVigencias($pdo);
        $this->ok(count($pend).' migraciones aplicadas · vigencias corregidas una sola vez');

        $this->paso('D3 · Cachés (config, rutas, vistas)');
        $this->artisan($kernel, 'config:cache');
        $this->artisan($kernel, 'route:cache');
        $this->artisan($kernel, 'view:cache');
        $this->estado['route_cache'] = true;
        $this->ok('config, rutas y vistas cacheadas');
    }

    private function verificarVigencias(PDO $pdo): void
    {
        $antes = (array) ($this->estado['vigencias_antes'] ?? []);
        $despues = $this->vigencias($pdo);
        $ok = 0;
        foreach ($antes as $tabla => $filas) {
            foreach ($filas as $id => $cols) {
                foreach ($cols as $c => $valor) {
                    if ($valor === null) {
                        continue;
                    }
                    $esperado = gmdate('Y-m-d H:i:s', (int) strtotime($valor.' UTC') + 3 * 3600);
                    $real = $despues[$tabla][$id][$c] ?? null;
                    if ($real !== $esperado) {
                        throw new CfFallo("Vigencia {$tabla}#{$id}.{$c}: era {$valor}, quedó ".($real ?? 'NULL').' y se esperaba '.$esperado.' (+3 h exactas, una sola vez).');
                    }
                    $ok++;
                }
            }
        }
        $this->log("  vigencias: {$ok} fecha(s) corridas +3 h exactas (los NULL no se tocan)");
    }

    // ================================================================================================================
    // Fase E — verificación HTTP con el bypass
    // ================================================================================================================

    private function faseE(): void
    {
        $this->estado['fase'] = 'E';
        $this->paso('E1 · La tienda responde igual (con bypass de mantenimiento)');
        $this->gancho('verificacion');
        try {
            $this->verificarTienda();
        } catch (CfFallo $e) {
            // Las rutas con closures cacheadas se deserializan con un stream wrapper + include: si el hosting lo bloquea, la tienda da 500.
            $this->aviso('Falló la verificación con route:cache ('.$e->getMessage().'). Pruebo sin rutas cacheadas.');
            $kernel = $this->kernel();
            $this->artisan($kernel, 'route:clear');
            $this->estado['route_cache'] = false;
            $this->verificarTienda();
            $this->estado['manual'][] = 'route:cache NO se usa en este hosting (la verificación falló con las rutas cacheadas y anduvo sin ellas).';
            $this->aviso('Anduvo sin route:cache: queda sin rutas cacheadas.');
        }
        $this->paso('E2 · Integración apagada y laravel.log');
        $this->verificarIntegracionApagada();
    }

    private function verificarTienda(): void
    {
        foreach (self::RUTAS_IGUALES as $r) {
            $antes = $this->httpAntes[$r];
            $ahora = $this->http($r, ['jar' => true]);
            if ($antes['code'] === 200 && ($ahora['code'] !== 200 || $ahora['inertia'] !== $antes['inertia'])) {
                throw new CfFallo("{$r} respondía {$antes['code']}".($antes['inertia'] ? ' (Inertia)' : '')." y ahora {$ahora['code']}".($ahora['inertia'] ? ' (Inertia)' : '').' '.$ahora['error']);
            }
            if ($antes['titulo'] !== '' && $ahora['titulo'] !== $antes['titulo']) {
                $this->aviso("El título de {$r} cambió: «{$antes['titulo']}» → «{$ahora['titulo']}» (puede ser normal con el código nuevo).");
            }
            $this->ok(sprintf('%-20s →  %s | %s', $r, $ahora['code'], $ahora['titulo']));
        }
        foreach (['/carrito' => [200]] as $r => $cods) {
            $h = $this->http($r, ['jar' => true]);
            if (! in_array($h['code'], $cods, true)) {
                throw new CfFallo("{$r} (nuevo) responde {$h['code']}; se esperaba 200.");
            }
            $this->ok("{$r}  →  200");
        }
        $h = $this->http('/carrito/checkout', ['jar' => true]);
        if (! ($h['code'] === 200 || ($h['code'] === 302 && str_contains((string) $h['loc'], '/carrito')))) {
            throw new CfFallo("/carrito/checkout responde {$h['code']}; se esperaba 200 (o 302 a /carrito con el carrito vacío).");
        }
        $this->ok("/carrito/checkout  →  {$h['code']}".($h['loc'] ? ' → '.$h['loc'] : '').' (sin productos en el carrito redirige a /carrito)');
        // El requerimiento pide /checkout: en este proyecto la ruta es /carrito/checkout; /checkout no existe y es 404 de la app.
        $h = $this->http('/robots.txt', ['jar' => true]);
        if ($h['code'] !== 200 || ! str_contains($h['cuerpo'], 'Sitemap:')) {
            throw new CfFallo("/robots.txt responde {$h['code']} (se esperaba el de la app, con «Sitemap:»). ¿Quedó el robots.txt estático?");
        }
        $this->ok('/robots.txt  →  200 (lo sirve la app)');
        $h = $this->http('/sitemap.xml', ['jar' => true]);
        if ($h['code'] !== 200 || ! str_contains($h['cuerpo'], '<urlset')) {
            throw new CfFallo("/sitemap.xml responde {$h['code']}.");
        }
        $this->ok('/sitemap.xml  →  200');
        if (preg_match('#/productos/(\d+)#', $h['cuerpo'], $m)) {
            $p = $this->http('/productos/'.$m[1], ['jar' => true]);
            if ($p['code'] !== 200) {
                throw new CfFallo("/productos/{$m[1]} responde {$p['code']}.");
            }
            $this->ok("/productos/{$m[1]}  →  200 | {$p['titulo']}");
        } else {
            $this->aviso('El sitemap no lista productos activos: no se probó una ficha.');
        }
        $bm = json_decode((string) file_get_contents($this->publico.'/build/manifest.json'), true);
        $asset = null;
        foreach ((array) $bm as $e) {
            if (isset($e['file']) && preg_match('/\.(js|css)$/', (string) $e['file'])) {
                $asset = $e['file'];
                break;
            }
        }
        if ($asset) {
            $a = $this->http('/build/'.$asset, ['jar' => true]);
            if ($a['code'] !== 200) {
                throw new CfFallo("/build/{$asset} responde {$a['code']}.");
            }
            $this->ok("/build/{$asset}  →  200");
        }
        $l = $this->http('/chispas-frias-logo.png', ['jar' => true]);
        if (! in_array($l['code'], [200, 404], true)) {
            throw new CfFallo("/chispas-frias-logo.png responde {$l['code']}.");
        }
        $w = $this->http('/rukoz/webhook', ['jar' => true, 'post' => '{}', 'cabeceras' => ['Content-Type: application/json']]);
        // Este proyecto tiene Route::fallback (GET): un POST a una ruta que no existe da 405 en vez de 404. Lo que importa es que NO sea la
        // respuesta firmada del webhook (401/200 con JSON): esa sólo existe con la integración encendida.
        if (! in_array($w['code'], [404, 405], true) || str_contains($w['cuerpo'], '"codigo"')) {
            throw new CfFallo("POST /rukoz/webhook responde {$w['code']} con la integración apagada; se esperaba 404 (o 405 por el fallback de la web).");
        }
        $this->ok("POST /rukoz/webhook  →  {$w['code']} (la ruta no existe: integración apagada)");
        foreach (['/.env', '/vendor/autoload.php', '/storage/logs/laravel.log', '/composer.json'] as $r) {
            $x = $this->http($r, ['jar' => true]);
            if (! in_array($x['code'], [403, 404], true)) {
                throw new CfFallo("{$r} responde {$x['code']}: ¡se está sirviendo un archivo del proyecto!");
            }
        }
        $this->ok('/.env, /vendor/autoload.php, /storage/logs/laravel.log y /composer.json  →  403/404');
    }

    private function verificarIntegracionApagada(): void
    {
        $kernel = $this->kernel();
        $salida = new Symfony\Component\Console\Output\BufferedOutput();
        $codigo = $kernel->call('chispasfrias:integracion-estado', [], $salida);
        foreach (explode("\n", trim($salida->fetch())) as $l) {
            $this->log('    '.$l);
        }
        if ($codigo !== 0) {
            throw new CfFallo('chispasfrias:integracion-estado terminó con código '.$codigo.'.');
        }
        $salida = new Symfony\Component\Console\Output\BufferedOutput();
        $kernel->call('schedule:list', [], $salida);
        $this->log('schedule:list → '.trim(preg_replace('/\s+/', ' ', $salida->fetch())));
        $nuevos = $this->contarErroresLog() - $this->erroresLogAntes;
        if ($nuevos > 0) {
            $lineas = array_slice(array_filter(file($this->laravel.'/storage/logs/laravel.log') ?: [], static fn (string $l): bool => (bool) preg_match('/\.(ERROR|CRITICAL|ALERT|EMERGENCY): /', $l)), -5);
            foreach ($lineas as $l) {
                $this->log('    '.substr(trim($l), 0, 300), 'ERROR');
            }
            throw new CfFallo("laravel.log tiene {$nuevos} error(es) nuevo(s).");
        }
        $this->ok('Sin errores nuevos en laravel.log · integraciones apagadas');
    }

    // ================================================================================================================
    // Fase F — sitio arriba y cierre
    // ================================================================================================================

    private function faseF(): void
    {
        $this->estado['fase'] = 'F';
        $this->paso('F1 · Sitio arriba y comprobación pública');
        $this->gancho('antes_up');
        $this->quitarMantenimiento();
        foreach (['/', '/productos', '/carrito'] as $r) {
            $h = $this->http($r, ['jar' => false]);
            if ($h['code'] !== 200) {
                throw new CfFallo("{$r} responde {$h['code']} al público después de subir el sitio.");
            }
            $this->ok("{$r}  →  200 | {$h['titulo']}");
        }

        $this->paso('F2 · .env a 600 y limpieza');
        $envPath = $this->laravel.'/.env';
        $antes = fileperms($envPath) & 0777;
        @chmod($envPath, 0600);
        $a = $this->http('/', ['jar' => false]);
        $b = $this->http('/productos', ['jar' => false]);
        if ($a['code'] === 200 && $b['code'] === 200) {
            $this->ok('.env: '.decoct($antes).' → 600 · la tienda sigue respondiendo 200');
        } else {
            @chmod($envPath, $antes);
            $this->aviso('Con el .env en 600 la tienda respondió '.$a['code'].' / '.$b['code'].': se dejó en '.decoct($antes).'.');
            $this->estado['manual'][] = 'El .env quedó en '.decoct($antes).' porque con 600 la tienda no respondía: pedir a DonWeb que el usuario de PHP-FPM pueda leerlo.';
        }
        // Los assets del build anterior se conservan unos días: una página abierta en un navegador sigue pidiendo sus chunks viejos.
        $viejos = $this->backup.'/public_html-build.viejo/assets';
        $n = 0;
        if (is_dir($viejos)) {
            foreach (scandir($viejos) ?: [] as $f) {
                if ($f !== '.' && $f !== '..' && is_file($viejos.'/'.$f) && ! file_exists($this->publico.'/build/assets/'.$f)) {
                    copy($viejos.'/'.$f, $this->publico.'/build/assets/'.$f);
                    $n++;
                }
            }
        }
        $this->ok("assets del build anterior conservados en public_html/build/assets: {$n}");

        $this->estado['manual'] = array_merge((array) $this->estado['manual'], [
            'Probar a mano: un pedido de prueba completo (flujo sin Central) y cancelarlo; entrar al admin.',
            'Integración: NO se encendió nada. Los pasos 5 en adelante de la guía (variables del Central, rukoz:ping) necesitan una vía para correr artisan sin SSH.',
            'Backup (conservar hasta dar por bueno el deploy): '.$this->backup,
            'Con la integración apagada no hace falta ningún cron de Laravel (schedule:run). El cron de Ferozo sólo corre este runner.',
        ]);
    }

    private function quitarMantenimiento(): void
    {
        $dir = $this->laravel.'/storage/framework';
        foreach (['down', 'maintenance.php'] as $f) {
            if (is_file($dir.'/'.$f)) {
                @unlink($dir.'/'.$f);
            }
        }
        if (is_file($dir.'/down')) {
            throw new CfFallo('No pude sacar el sitio de mantenimiento (storage/framework/down sigue ahí).');
        }
        $this->estado['tocado']['mantenimiento'] = false;
        $this->guardarEstado();
    }

    // ================================================================================================================
    // Vuelta atrás (PHP puro: sirve también tras una caída del proceso)
    // ================================================================================================================

    private function vueltaAtras(): bool
    {
        $t = (array) ($this->estado['tocado'] ?? []);
        $fallos = 0;
        $paso = function (string $que, callable $f) use (&$fallos): void {
            try {
                $f();
                $this->log('  vuelta atrás: '.$que.' ✓');
            } catch (Throwable $e) {
                $fallos++;
                $this->log('  vuelta atrás: '.$que.' FALLÓ — '.$e->getMessage(), 'ERROR');
            }
        };

        if (empty($t['mantenimiento']) && ! empty($t['codigo']) && $this->secreto !== '') {
            // La falla fue después de `up` (verificación pública): se vuelve a poner el sitio en mantenimiento mientras se deshace.
            $paso('sitio de nuevo en mantenimiento', fn () => $this->escribirMantenimiento());
        }
        if (! empty($t['base'])) {
            $paso('base restaurada desde base.sql.gz', function (): void {
                $env = $this->parseEnv($this->backup.'/env.bak');
                $this->restaurarBase($this->pdo($env), $this->backup.'/base.sql.gz', (array) ($this->estado['dump']['filas'] ?? []));
            });
        }
        if (! empty($t['htaccess'])) {
            $paso('.htaccess anterior', function (): void {
                $c = $this->backup.'/public_html-htaccess.copia';
                if (is_file($c)) {
                    copy($c, $this->publico.'/.htaccess');
                }
            });
        }
        if (! empty($t['estaticos'])) {
            $paso('estáticos de public_html', function () use ($t): void {
                foreach ((array) $t['estaticos'] as $rel => $existia) {
                    $dst = $this->publico.'/'.$rel;
                    if ($existia) {
                        copy($this->backup.'/public_html-estaticos/'.$rel, $dst);
                    } elseif (is_file($dst)) {
                        unlink($dst);
                    }
                }
            });
        }
        if (! empty($t['borrados'])) {
            $paso('archivos borrados de public_html repuestos', function () use ($t): void {
                foreach ((array) $t['borrados'] as $rel) {
                    $c = $this->backup.'/public_html-'.ltrim($rel, '.').'.copia';
                    if (is_file($c)) {
                        copy($c, $this->publico.'/'.$rel);
                    }
                }
            });
        }
        if (! empty($t['build'])) {
            $paso('public_html/build anterior', function (): void {
                $v = $this->backup.'/public_html-build.viejo';
                if (is_dir($v)) {
                    $this->rmtree($this->publico.'/build', true);
                    if (! rename($v, $this->publico.'/build')) {
                        throw new CfFallo('rename del build viejo falló');
                    }
                }
            });
        }
        if (! empty($t['codigo'])) {
            $paso('código anterior (laravel/ con su vendor, storage, .env y public)', fn () => $this->deshacerCodigo());
        }
        if (! empty($t['env']) || ! empty($t['codigo'])) {
            $paso('.env original', function (): void {
                if (is_file($this->backup.'/env.bak') && is_dir($this->laravel)) {
                    copy($this->backup.'/env.bak', $this->laravel.'/.env');
                    @chmod($this->laravel.'/.env', (int) ($this->estado['env_mode'] ?? 0644));
                }
            });
        }
        $paso('sitio arriba', function (): void {
            $dir = $this->laravel.'/storage/framework';
            foreach (['down', 'maintenance.php'] as $f) {
                if (is_file($dir.'/'.$f)) {
                    unlink($dir.'/'.$f);
                }
            }
            if (is_file($dir.'/down')) {
                throw new CfFallo('storage/framework/down sigue ahí');
            }
        });
        if ($this->urlBase !== '') {
            $paso('la tienda responde 200 con el código anterior', function (): void {
                $h = $this->http('/', ['jar' => false]);
                if ($h['code'] !== 200) {
                    throw new CfFallo('/ responde '.$h['code'].' '.$h['error']);
                }
            });
        }

        return $fallos === 0;
    }

    private function deshacerCodigo(): void
    {
        $v = $this->backup.'/laravel.viejo';
        $n = $this->work.'/laravel.nuevo';
        $donde = $n; // dónde quedaron las piezas que se movieron
        if (is_dir($v)) {
            if (is_dir($this->laravel)) {
                $donde = $this->backup.'/laravel.fallido';
                $this->rmtree($donde, true);
                if (! rename($this->laravel, $donde)) {
                    throw new CfFallo('no pude apartar el laravel/ nuevo');
                }
            }
            if (! rename($v, $this->laravel)) {
                throw new CfFallo('no pude devolver laravel.viejo');
            }
        }
        foreach (['storage', '.env', 'public'] as $pieza) {
            if (! file_exists($this->laravel.'/'.$pieza) && file_exists($donde.'/'.$pieza)) {
                if (! rename($donde.'/'.$pieza, $this->laravel.'/'.$pieza)) {
                    throw new CfFallo("no pude devolver {$pieza}");
                }
            }
        }
        $b = $this->backup.'/laravel-public-build.viejo';
        if (is_dir($b)) {
            $this->rmtree($this->laravel.'/public/build', true);
            if (! rename($b, $this->laravel.'/public/build')) {
                throw new CfFallo('no pude devolver laravel/public/build');
            }
        }
        foreach (['bootstrap/cache'] as $d) {
            if (! is_dir($this->laravel.'/'.$d)) {
                @mkdir($this->laravel.'/'.$d, 0775, true);
            }
        }
    }

    // ================================================================================================================
    // Laravel en el proceso (sólo se llama después de cambiar el código, o en el ensayo, una única vez)
    // ================================================================================================================

    /** @var mixed */
    private $kernelObj = null;

    private function cargarLaravel(string $raiz): object
    {
        if ($this->laravelCargado) {
            throw new CfFallo('Laravel ya está cargado en este proceso (no se puede cargar dos veces).');
        }
        $this->laravelCargado = true;
        if (! defined('LARAVEL_START')) {
            define('LARAVEL_START', microtime(true));
        }
        chdir($raiz);
        require $raiz.'/vendor/autoload.php';
        $app = require $raiz.'/bootstrap/app.php';
        $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();
        $this->kernelObj = $kernel;

        return $kernel;
    }

    private function kernel(): object
    {
        return $this->kernelObj ?? throw new CfFallo('Laravel no está cargado.');
    }

    private function artisan(object $kernel, string $comando, array $args = []): string
    {
        $salida = new Symfony\Component\Console\Output\BufferedOutput();
        $codigo = $kernel->call($comando, $args, $salida);
        $texto = trim($salida->fetch());
        foreach (explode("\n", $texto) as $l) {
            if (trim($l) !== '') {
                $this->log('    artisan '.$comando.': '.trim($l));
            }
        }
        if ($codigo !== 0) {
            throw new CfFallo("artisan {$comando} terminó con código {$codigo}.");
        }

        return $texto;
    }

    // ================================================================================================================
    // Base de datos: volcado y restauración por PDO
    // ================================================================================================================

    /** @return array{tablas: list<string>, vistas: list<string>} */
    private function listarTablas(PDO $pdo): array
    {
        $tablas = [];
        $vistas = [];
        foreach ($pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM) as $f) {
            if (strtoupper((string) $f[1]) === 'VIEW') {
                $vistas[] = (string) $f[0];
            } else {
                $tablas[] = (string) $f[0];
            }
        }

        return ['tablas' => $tablas, 'vistas' => $vistas];
    }

    /**
     * Volcado completo (estructura + datos) en .sql.gz. Formato: cada sentencia termina con una línea «-- @@» (los datos van
     * escapados y nunca llevan saltos de línea reales, así que el restaurador no necesita un parser de SQL).
     *
     * @return array{filas: array<string, int>}
     */
    private function volcarBase(PDO $pdo, string $destino): array
    {
        $gz = gzopen($destino, 'wb6');
        if ($gz === false) {
            throw new CfFallo("No puedo escribir {$destino}.");
        }
        $w = static function (string $sql) use ($gz): void {
            gzwrite($gz, $sql."\n-- @@\n");
        };
        $pdo->exec("SET SESSION sql_mode='NO_AUTO_VALUE_ON_ZERO'");
        $pdo->exec("SET SESSION time_zone='+00:00'");
        $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
        $w('-- Volcado de runner.php '.self::VERSION.' · '.gmdate('c'));
        $w("SET NAMES utf8mb4");
        $w("SET SESSION sql_mode='NO_AUTO_VALUE_ON_ZERO'");
        $w("SET SESSION time_zone='+00:00'");
        $w('SET FOREIGN_KEY_CHECKS=0');
        $w('SET UNIQUE_CHECKS=0');
        $filas = [];
        $lista = $this->listarTablas($pdo);
        foreach ($lista['tablas'] as $t) {
            $q = '`'.str_replace('`', '``', $t).'`';
            $crear = $pdo->query("SHOW CREATE TABLE {$q}")->fetch(PDO::FETCH_NUM);
            $w("DROP TABLE IF EXISTS {$q}");
            $w((string) $crear[1]);
            $cols = [];
            $tipos = [];
            foreach ($pdo->query("SHOW FULL COLUMNS FROM {$q}")->fetchAll(PDO::FETCH_ASSOC) as $c) {
                if (stripos((string) $c['Extra'], 'GENERATED') !== false) {
                    continue;
                }
                $tipo = strtolower((string) $c['Type']);
                if (preg_match('/^(geometry|point|linestring|polygon|multipoint|multilinestring|multipolygon|geometrycollection)/', $tipo)) {
                    throw new CfFallo("La tabla {$t} tiene una columna espacial ({$c['Field']}): el volcado del runner no la soporta.");
                }
                $cols[] = '`'.str_replace('`', '``', (string) $c['Field']).'`';
                $tipos[] = preg_match('/^(tiny|medium|long)?blob|^binary|^varbinary|^bit/', $tipo) ? 'bin' : (preg_match('/^(tinyint|smallint|mediumint|int|integer|bigint|decimal|numeric|float|double|real|year)/', $tipo) ? 'num' : 'txt');
            }
            $n = 0;
            $lote = [];
            $bytesLote = 0;
            $cab = "INSERT INTO {$q} (".implode(',', $cols).') VALUES ';
            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false); // las tablas grandes se leen de a una fila
            $it = $pdo->query('SELECT '.implode(',', $cols)." FROM {$q}");
            while (($fila = $it->fetch(PDO::FETCH_NUM)) !== false) {
                $vals = [];
                foreach ($fila as $i => $v) {
                    if ($v === null) {
                        $vals[] = 'NULL';
                    } elseif ($tipos[$i] === 'bin') {
                        $vals[] = $v === '' ? "''" : '0x'.bin2hex((string) $v);
                    } elseif ($tipos[$i] === 'num' && preg_match('/^-?\d+(\.\d+)?([eE][+-]?\d+)?$/', (string) $v)) {
                        $vals[] = (string) $v;
                    } else {
                        $vals[] = $pdo->quote((string) $v);
                    }
                }
                $tupla = '('.implode(',', $vals).')';
                $lote[] = $tupla;
                $bytesLote += strlen($tupla);
                $n++;
                if (count($lote) >= 200 || $bytesLote > 700000) {
                    $w($cab.implode(',', $lote));
                    $lote = [];
                    $bytesLote = 0;
                }
            }
            $it->closeCursor();
            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
            if ($lote) {
                $w($cab.implode(',', $lote));
            }
            $filas[$t] = $n;
        }
        foreach ($lista['vistas'] as $vista) {
            $q = '`'.str_replace('`', '``', $vista).'`';
            $crear = $pdo->query("SHOW CREATE VIEW {$q}")->fetch(PDO::FETCH_NUM);
            $w("DROP VIEW IF EXISTS {$q}");
            $w((string) $crear[1]);
        }
        foreach ($pdo->query('SHOW TRIGGERS')->fetchAll(PDO::FETCH_ASSOC) as $tr) {
            $nom = '`'.str_replace('`', '``', (string) $tr['Trigger']).'`';
            $crear = $pdo->query("SHOW CREATE TRIGGER {$nom}")->fetch(PDO::FETCH_ASSOC);
            $w("DROP TRIGGER IF EXISTS {$nom}");
            $w((string) $crear['SQL Original Statement']);
        }
        $w('SET FOREIGN_KEY_CHECKS=1');
        $w('-- FIN DEL VOLCADO '.count($filas).' tablas');
        $pdo->exec('COMMIT');
        gzclose($gz);

        return ['filas' => $filas];
    }

    /** @param array{filas: array<string, int>} $r */
    private function verificarDump(string $gz, array $r): void
    {
        $h = gzopen($gz, 'rb');
        if ($h === false) {
            throw new CfFallo('El volcado no se puede leer.');
        }
        $creates = 0;
        $fin = false;
        $buf = '';
        while (! gzeof($h)) {
            $buf .= (string) gzread($h, 1048576);
            $creates += preg_match_all('/^CREATE TABLE /m', $buf);
            $fin = str_contains($buf, '-- FIN DEL VOLCADO');
            $buf = substr($buf, -64);
        }
        gzclose($h);
        if ($creates < count($r['filas']) || ! $fin) {
            throw new CfFallo('El volcado quedó incompleto ('.$creates.' CREATE TABLE para '.count($r['filas']).' tablas'.($fin ? '' : ', sin marca de fin').').');
        }
        if (filesize($gz) < 400) {
            throw new CfFallo('El volcado es sospechosamente chico.');
        }
    }

    /** @param array<string, int> $filas */
    private function restaurarBase(PDO $pdo, string $gz, array $filas): void
    {
        $pdo->exec("SET SESSION sql_mode='NO_AUTO_VALUE_ON_ZERO'");
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach ($pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM) as $f) {
            $q = '`'.str_replace('`', '``', (string) $f[0]).'`';
            $pdo->exec((strtoupper((string) $f[1]) === 'VIEW' ? 'DROP VIEW IF EXISTS ' : 'DROP TABLE IF EXISTS ').$q);
        }
        $h = gzopen($gz, 'rb');
        if ($h === false) {
            throw new CfFallo('No puedo leer '.$gz);
        }
        $sentencia = '';
        $linea = '';
        $n = 0;
        while (! gzeof($h)) {
            $trozo = gzgets($h, 65536);
            if ($trozo === false) {
                break;
            }
            $linea .= $trozo;
            if (! str_ends_with($linea, "\n")) {
                continue;
            }
            if (rtrim($linea, "\r\n") === '-- @@') {
                if (trim($sentencia) !== '' && ! str_starts_with(ltrim($sentencia), '--')) {
                    $pdo->exec($sentencia);
                    $n++;
                }
                $sentencia = '';
            } else {
                $sentencia .= $linea;
            }
            $linea = '';
        }
        gzclose($h);
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        foreach ($filas as $t => $esperadas) {
            $q = '`'.str_replace('`', '``', $t).'`';
            $real = (int) $pdo->query("SELECT COUNT(*) FROM {$q}")->fetchColumn();
            if ($real !== (int) $esperadas) {
                throw new CfFallo("Tabla {$t} restaurada con {$real} filas (el backup tenía {$esperadas}).");
            }
        }
        $this->log("  base restaurada: {$n} sentencias, ".count($filas).' tablas con las mismas filas que el backup');
    }

    /** @return list<string> */
    private function migracionesAplicadas(PDO $pdo): array
    {
        try {
            return array_map('strval', $pdo->query('SELECT migration FROM migrations')->fetchAll(PDO::FETCH_COLUMN));
        } catch (Throwable) {
            return [];
        }
    }

    /** @param list<string> $aplicadas @return list<string> */
    private function migracionesPendientes(array $aplicadas, ?string $raiz = null): array
    {
        $raiz ??= $this->work.'/laravel.nuevo';
        $archivos = [];
        foreach ([$raiz.'/database/migrations', $raiz.'/vendor/rukoz/core-client/database/migrations'] as $dir) {
            foreach (glob($dir.'/*.php') ?: [] as $f) {
                $archivos[] = basename($f, '.php');
            }
        }
        $pend = array_values(array_diff($archivos, $aplicadas));
        sort($pend);

        return $pend;
    }

    /** @return array<string, array<int|string, array<string, ?string>>> */
    private function vigencias(PDO $pdo): array
    {
        $r = [];
        foreach (['product_offers', 'discount_codes'] as $t) {
            try {
                foreach ($pdo->query("SELECT id, start_date, end_date FROM `{$t}` ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $f) {
                    $r[$t][$f['id']] = ['start_date' => $f['start_date'], 'end_date' => $f['end_date']];
                }
            } catch (Throwable) {
                // la tabla no existe en esta base
            }
            $r[$t] ??= [];
        }

        return $r;
    }

    /** @param array<string, array<int|string, array<string, ?string>>> $v */
    private function vigenciasTexto(array $v): string
    {
        $s = "# Vigencias (UTC) antes de la migración 2026_10_19_130000_corregir_vigencias_a_utc, que suma 3 h una sola vez\n";
        foreach ($v as $t => $filas) {
            foreach ($filas as $id => $c) {
                $s .= "{$t} #{$id}  ".($c['start_date'] ?? 'NULL').' -> '.($c['end_date'] ?? 'NULL')."\n";
            }
        }

        return $s;
    }

    // ================================================================================================================
    // HTTP
    // ================================================================================================================

    /** @return array{code: int, titulo: string, inertia: bool, cuerpo: string, loc: ?string, error: string} */
    private function http(string $ruta, array $o = []): array
    {
        $ch = curl_init($this->urlBase.$ruta);
        $loc = null;
        $cab = array_merge(['Accept: text/html,application/json,*/*', 'Cache-Control: no-cache'], (array) ($o['cabeceras'] ?? []));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 40, CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_USERAGENT => 'ChispasFrias-Deploy/'.self::VERSION, CURLOPT_HTTPHEADER => $cab,
            CURLOPT_HEADERFUNCTION => static function ($c, string $l) use (&$loc): int {
                if (stripos($l, 'location:') === 0) {
                    $loc = trim(substr($l, 9));
                }

                return strlen($l);
            },
        ]);
        if (! empty($o['jar'])) {
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->jar);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $this->jar);
        }
        if (isset($o['post'])) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $o['post']);
        }
        $cuerpo = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = $cuerpo === false ? curl_error($ch) : '';
        curl_close($ch);
        $cuerpo = is_string($cuerpo) ? substr($cuerpo, 0, 2000000) : '';
        $titulo = '';
        if (preg_match('#<title[^>]*>(.*?)</title>#is', $cuerpo, $m)) {
            $titulo = trim(html_entity_decode($m[1]));
        }

        return ['code' => $code, 'titulo' => $titulo, 'inertia' => str_contains($cuerpo, 'data-page='), 'cuerpo' => $cuerpo, 'loc' => $loc, 'error' => $error];
    }

    private function contarErroresLog(): int
    {
        $f = $this->laravel.'/storage/logs/laravel.log';
        if (! is_file($f)) {
            return 0;
        }
        $n = 0;
        $h = fopen($f, 'rb');
        while (($l = fgets($h, 65536)) !== false) {
            if (preg_match('/^\[[^\]]+\] \S+\.(ERROR|CRITICAL|ALERT|EMERGENCY): /', $l)) {
                $n++;
            }
        }
        fclose($h);

        return $n;
    }

    // ================================================================================================================
    // Utilidades
    // ================================================================================================================

    /** @return array<string, string> */
    private function parseEnv(string $path): array
    {
        $r = [];
        foreach (preg_split('/\R/', (string) @file_get_contents($path)) ?: [] as $l) {
            $l = trim($l);
            if ($l === '' || $l[0] === '#' || ! str_contains($l, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $l, 2);
            $k = trim(preg_replace('/^export\s+/', '', $k));
            $v = trim($v);
            if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && substr($v, -1) === $v[0]) {
                $v = substr($v, 1, -1);
            } elseif (($p = strpos($v, ' #')) !== false) {
                $v = rtrim(substr($v, 0, $p));
            }
            $r[$k] = $v;
        }

        return $r;
    }

    private function comillas(string $v): string
    {
        return preg_match('/^[A-Za-z0-9_.:\/@+,-]*$/', $v) ? $v : '"'.addcslashes($v, "\"\\\$").'"';
    }

    private function setEnv(string $path, string $clave, string $valor): void
    {
        $lineas = preg_split('/\R/', rtrim((string) file_get_contents($path), "\r\n")) ?: [];
        $hecho = false;
        $salida = [];
        foreach ($lineas as $l) {
            if (preg_match('/^\s*(export\s+)?'.preg_quote($clave, '/').'\s*=/', $l)) {
                if (! $hecho) {
                    $salida[] = $clave.'='.$valor;
                    $hecho = true;
                }

                continue;
            }
            $salida[] = $l;
        }
        if (! $hecho) {
            $salida[] = $clave.'='.$valor;
        }
        file_put_contents($path, implode("\n", $salida)."\n");
    }

    private function dentroDeBasedir(string $ruta): bool
    {
        $ob = (string) ini_get('open_basedir');
        if ($ob === '') {
            return true;
        }
        foreach (explode(PATH_SEPARATOR, $ob) as $p) {
            $p = rtrim(str_replace('\\', '/', $p), '/');
            if ($p !== '' && str_starts_with(str_replace('\\', '/', $ruta).'/', $p.'/')) {
                return true;
            }
        }

        return false;
    }

    private function rmtree(string $dir, bool $silencioso = false): void
    {
        if (! file_exists($dir) && ! is_link($dir)) {
            return;
        }
        $real = realpath($dir);
        $permitidos = [$this->work, $this->backups, $this->home.'/.cf-prueba-rename', $this->publico.'/build', $this->laravel.'/public/build'];
        $ok = false;
        foreach ($permitidos as $p) {
            $pr = realpath($p) ?: $p;
            if ($real !== false && str_starts_with(str_replace('\\', '/', $real).'/', str_replace('\\', '/', $pr).'/')) {
                $ok = true;
            }
        }
        if (! $ok) {
            throw new CfFallo("rmtree se niega a borrar {$dir} (fuera de las carpetas del runner).");
        }
        if (is_file($dir) || is_link($dir)) {
            @unlink($dir);

            return;
        }
        foreach (scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $p = $dir.'/'.$f;
            if (is_dir($p) && ! is_link($p)) {
                $this->rmtree($p, $silencioso);
            } else {
                @unlink($p);
            }
        }
        @rmdir($dir);
    }

    private function copiarArbol(string $src, string $dst): void
    {
        @mkdir($dst, 0755, true);
        foreach (scandir($src) ?: [] as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            if (is_dir($src.'/'.$f)) {
                $this->copiarArbol($src.'/'.$f, $dst.'/'.$f);
            } else {
                copy($src.'/'.$f, $dst.'/'.$f);
                @chmod($dst.'/'.$f, 0644);
            }
        }
    }

    private function copiaYBorra(string $origen, string $copia): void
    {
        copy($origen, $copia);
        unlink($origen);
    }

    private function contarArchivos(string $dir): int
    {
        $n = 0;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $f) {
            $n += $f->isFile() ? 1 : 0;
        }

        return $n;
    }

    private function tamanoArchivo(string $f): int
    {
        return is_file($f) ? (int) filesize($f) : 0;
    }

    /** @param list<string> $excluir */
    private function tamanoArbol(string $dir, array $excluir): int
    {
        if (! is_dir($dir)) {
            return 0;
        }
        $total = 0;
        $dir = rtrim(str_replace('\\', '/', $dir), '/');
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $f) {
            if (! $f->isFile() || $f->isLink()) {
                continue;
            }
            $rel = substr(str_replace('\\', '/', $f->getPathname()), strlen($dir) + 1);
            if ($this->excluido($rel, $excluir)) {
                continue;
            }
            $total += (int) $f->getSize();
        }

        return $total;
    }

    /** @param list<string> $excluir */
    private function excluido(string $rel, array $excluir): bool
    {
        foreach ($excluir as $e) {
            if ($rel === $e || str_starts_with($rel, $e.'/')) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $excluir */
    private function zipArbol(string $dir, string $prefijo, string $zipPath, array $excluir): int
    {
        $dir = rtrim(str_replace('\\', '/', $dir), '/');
        $archivos = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $f) {
            if (! $f->isFile() || $f->isLink()) {
                continue;
            }
            $rel = substr(str_replace('\\', '/', $f->getPathname()), strlen($dir) + 1);
            if (! $this->excluido($rel, $excluir)) {
                $archivos[$prefijo.'/'.$rel] = $f->getPathname();
            }
        }

        return $this->zipLista($archivos, $zipPath);
    }

    /** @param list<string> $rutas rutas relativas (archivos o carpetas) dentro de $dir */
    private function zipRutas(string $dir, string $prefijo, string $zipPath, array $rutas): int
    {
        $archivos = [];
        foreach ($rutas as $rel) {
            $p = $dir.'/'.$rel;
            if (is_file($p)) {
                $archivos[$prefijo.'/'.$rel] = $p;
            } elseif (is_dir($p)) {
                foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($p, FilesystemIterator::SKIP_DOTS)) as $f) {
                    if ($f->isFile() && ! $f->isLink()) {
                        $archivos[$prefijo.'/'.substr(str_replace('\\', '/', $f->getPathname()), strlen(str_replace('\\', '/', $dir)) + 1)] = $f->getPathname();
                    }
                }
            }
        }

        return $this->zipLista($archivos, $zipPath);
    }

    /** @param array<string, string> $archivos nombre en el zip => ruta real */
    private function zipLista(array $archivos, string $zipPath): int
    {
        @unlink($zipPath);
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new CfFallo("No pude crear {$zipPath}.");
        }
        $n = 0;
        foreach ($archivos as $nombre => $ruta) {
            $zip->addFile($ruta, $nombre);
            $n++;
            if ($n % 200 === 0) { // addFile deja el archivo abierto hasta close(): se cierra seguido para no pasar el límite de descriptores
                $zip->close();
                $zip = new ZipArchive();
                $zip->open($zipPath);
            }
        }
        if ($zip->close() !== true) {
            throw new CfFallo("No pude cerrar {$zipPath} (¿sin espacio?).");
        }
        $z = new ZipArchive();
        if ($z->open($zipPath, ZipArchive::RDONLY) !== true || $z->numFiles !== $n) {
            throw new CfFallo("El backup {$zipPath} quedó incompleto.");
        }
        $z->close();
        @chmod($zipPath, 0600);

        return $n;
    }

    private function limpiar(): void
    {
        if (str_starts_with((string) getenv('CF_RUNNER_PRUEBA'), 'muere:')) {
            return; // la prueba de caída necesita work/ para que la corrida siguiente pueda deshacer
        }
        try {
            foreach (['pkg', 'laravel.nuevo', 'public_html.nuevo', 'prueba-rename'] as $d) {
                $this->rmtree($this->work.'/'.$d, true);
            }
            @unlink($this->jar);
        } catch (Throwable $e) {
            $this->log('No pude limpiar work/: '.$e->getMessage(), 'AVISO');
        }
    }

    private function gancho(string $punto): void
    {
        $p = getenv('CF_RUNNER_PRUEBA');
        if ($p === false) {
            return;
        }
        if ($p === 'falla:'.$punto) {
            throw new CfFallo("(prueba) falla inyectada en «{$punto}»");
        }
        if ($p === 'muere:'.$punto) {
            $this->log("(prueba) el proceso muere en «{$punto}»", 'AVISO');
            $this->guardarEstado();
            exit(99);
        }
    }

    // --- estado y log ---------------------------------------------------------------------------------------------

    private function paso(string $nombre): void
    {
        $this->cerrarPaso();
        $this->pasoActual = $nombre;
        $this->pasoT0 = microtime(true);
        $this->estado['paso'] = $nombre;
        $this->guardarEstado();
        $this->log('==> '.$nombre);
    }

    private function cerrarPaso(): void
    {
        if ($this->pasoActual !== null) {
            $this->estado['pasos'][] = ['n' => $this->pasoActual, 'seg' => round(microtime(true) - $this->pasoT0, 1), 'ok' => true];
            $this->pasoActual = null;
        }
    }

    private function ok(string $m): void
    {
        $this->log('OK  '.$m);
    }

    private function aviso(string $m): void
    {
        $this->estado['avisos'][] = $m;
        $this->log($m, 'AVISO');
    }

    private function log(string $m, string $nivel = 'INFO'): void
    {
        $linea = gmdate('Y-m-d H:i:s').' '.str_pad($nivel, 5).' '.$m."\n";
        @file_put_contents($this->logPath, $linea, FILE_APPEND);
        if ($this->backupLogFp) {
            fwrite($this->backupLogFp, $linea);
        }
        fwrite(STDOUT, $linea);
    }

    private function rotarLog(): void
    {
        if (is_file($this->logPath) && filesize($this->logPath) > 2 * 1048576) {
            @rename($this->logPath, $this->logPath.'.1');
            $this->estado['log_desde'] = 0;
        }
    }

    private function guardarEstado(): void
    {
        $this->estado['actualizado'] = gmdate('c');
        $tmp = $this->estadoPath.'.tmp';
        file_put_contents($tmp, json_encode($this->estado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR));
        @rename($tmp, $this->estadoPath);
    }

    /** @return array<string, mixed> */
    private function leerJson(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }
        $j = json_decode((string) file_get_contents($path), true);

        return is_array($j) ? $j : [];
    }
}

exit((new CfRunner())->ejecutar());
