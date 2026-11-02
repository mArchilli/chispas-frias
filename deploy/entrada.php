<?php
/**
 * Punto de entrada web del deploy de Chispas Frías (se instala en public_html/cf-deploy-<16 hex>.php con
 * `deploy/publicar.ps1 -InstalarEntrada`, que reemplaza __TOKEN_SHA256__ por el sha256 del token; el token en sí sólo vive en la tarea
 * del cron de Ferozo y en ~\.rukoz-deploy\chispasfrias-entrada.json de la PC).
 *
 * El cron de Ferozo sólo ejecuta `cronwget` (un pedido HTTPS), así que el motor del deploy (deploy-cf/runner.php) corre dentro de
 * PHP-FPM, cargado desde acá:
 *
 *   sin token válido            → 404 vacío, sin leer nada más.
 *   con token + ?ping           → 204 (prueba de red/concurrencia; no toca nada).
 *   con token y sin trabajo     → 204 y listo. «Trabajo» = hay disparo.json o estado.json dice «ejecutando».
 *   con token y con trabajo     → 204, se cierra la conexión y el motor sigue en segundo plano (ignore_user_abort + set_time_limit(0)).
 *
 * Todo lo que importa (candado, estado, vuelta atrás) está en el motor; esto es sólo la puerta. Se deja una marca de vida en
 * deploy-cf/cron-ultimo.txt para que publicar.ps1 -Estado muestre cuándo fue el último pedido del cron.
 */

const CF_ENTRADA_TOKEN_SHA256 = '__TOKEN_SHA256__';

$t = (isset($_GET['t']) && is_string($_GET['t'])) ? $_GET['t'] : '';
if ($t === '' || strlen($t) > 128 || ! hash_equals(CF_ENTRADA_TOKEN_SHA256, hash('sha256', $t))) {
    http_response_code(404);
    exit;
}

header('Cache-Control: no-store');
$base = dirname(__DIR__).'/deploy-cf';

if (isset($_GET['ping']) || ! is_dir($base)) {
    http_response_code(204);
    exit;
}

$ua = preg_replace('/[^\x20-\x7E]/', '?', substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? '-'), 0, 80));
@file_put_contents($base.'/cron-ultimo.txt', gmdate('c').' · '.PHP_SAPI.' · '.(isset($_GET['c']) ? 'encadenado' : (isset($_GET['g']) ? 'gatillo' : 'cron')).' · '.$ua."\n");

$hayTrabajo = is_file($base.'/disparo.json');
if (! $hayTrabajo && is_file($base.'/estado.json') && (int) @filesize($base.'/estado.json') < 2000000) {
    $e = json_decode((string) @file_get_contents($base.'/estado.json'), true);
    $hayTrabajo = is_array($e) && ($e['estado'] ?? '') === 'ejecutando';
}

http_response_code(204);
if (! $hayTrabajo || ! is_file($base.'/runner.php')) {
    exit;
}

ignore_user_abort(true);
@set_time_limit(0);
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    header('Connection: close');
    header('Content-Length: 0');
    flush();
}

define('CF_ENTRADA_WEB', true);
define('CF_ENTRADA_TOKEN', $t);
define('CF_ENTRADA_RUTA', '/'.basename(__FILE__));
require $base.'/runner.php';
