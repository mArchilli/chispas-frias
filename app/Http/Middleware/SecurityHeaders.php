<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad en todas las respuestas (S.2 · A.9). Basado en el `SecurityHeaders` del Sistema Central.
 *
 *  - Content-Security-Policy: sin JavaScript de terceros ni inline sin nonce (los inline llevan un nonce por request: el de Ziggy
 *    `@routes` y los que arma `@vite`), sin plugins, sin iframes, formularios y `<base>` solo al propio sitio. Arranca en modo
 *    «solo reportar» (config/seguridad.php → csp.modo): el navegador no bloquea nada y avisa a /csp-report.
 *  - Strict-Transport-Security (solo si el request llegó por HTTPS), X-Frame-Options / frame-ancestors, X-Content-Type-Options,
 *    Referrer-Policy, Permissions-Policy.
 *  - El panel (/admin) y el login no se indexan nunca (X-Robots-Tag).
 *
 * Lo que carga hoy la tienda: su propio JS/CSS (Vite), imágenes y videos propios (public_html/images, /videos) y la fuente
 * Poppins de fonts.bunny.net (hoja de estilos y archivos de fuente). `style-src 'unsafe-inline'` hace falta porque React escribe
 * atributos `style` (no admiten nonce); el JavaScript inline sin nonce y el `eval` siguen sin permitirse.
 * Los enlaces a WhatsApp / Instagram son navegaciones: la CSP no las limita.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Un nonce nuevo por request: lo usan `@vite` (scripts y estilos del build) y `@routes` (el script inline de Ziggy).
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), serial=(), bluetooth=(), accelerometer=(), gyroscope=(), magnetometer=(), interest-cohort=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        if ($request->is('admin', 'admin/*', 'login', 'register', 'forgot-password', 'reset-password/*', 'profile', 'confirm-password', 'verify-email', 'verify-email/*')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }

        $modo = (string) config('seguridad.csp.modo');

        if (in_array($modo, ['report-only', 'enforce'], true)) {
            $cabecera = $modo === 'enforce' ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only';
            $response->headers->set($cabecera, $this->politica($nonce, $request->isSecure()));
        }

        if ($request->isSecure()) {
            $hsts = 'max-age='.max(0, (int) config('seguridad.hsts.segundos'));

            if (config('seguridad.hsts.subdominios')) {
                $hsts .= '; includeSubDomains';
            }

            $response->headers->set('Strict-Transport-Security', $hsts);
        }

        return $response;
    }

    private function politica(string $nonce, bool $seguro): string
    {
        $propio = "'self'";
        $bunny = 'https://fonts.bunny.net';
        $desarrollo = $this->servidorDeDesarrollo();
        $extra = fn (string $clave): array => (array) config("seguridad.csp.extra_{$clave}", []);

        $directivas = [
            'default-src' => [$propio],
            'script-src' => [$propio, "'nonce-{$nonce}'", ...$desarrollo['http'], ...$extra('script')],
            // 'unsafe-inline' solo para ESTILOS (atributos style de React): el JavaScript inline sigue sin permitirse.
            'style-src' => [$propio, "'unsafe-inline'", $bunny, ...$desarrollo['http'], ...$extra('style')],
            'img-src' => [$propio, 'data:', 'blob:', ...$desarrollo['http'], ...$extra('img')],
            'font-src' => [$propio, 'data:', $bunny, ...$desarrollo['http'], ...$extra('font')],
            'connect-src' => [$propio, ...$desarrollo['http'], ...$desarrollo['ws'], ...$extra('connect')],
            'media-src' => [$propio, 'blob:'],
            'worker-src' => [$propio, 'blob:'],
            'manifest-src' => [$propio],
            'object-src' => ["'none'"],
            'frame-src' => $extra('frame') ?: ["'none'"],
            'frame-ancestors' => ["'none'"],
            'base-uri' => [$propio],
            'form-action' => [$propio],
        ];

        $texto = collect($directivas)->map(fn (array $valores, string $nombre) => $nombre.' '.implode(' ', $valores))->implode('; ');

        if ($seguro) {
            $texto .= '; upgrade-insecure-requests';
        }

        if (($reportar = config('seguridad.csp.reportar_a')) && $reportar !== 'false') {
            $texto .= '; report-uri '.$reportar;
        }

        return $texto;
    }

    /**
     * Con `npm run dev` los scripts y estilos vienen del servidor de Vite (otro origen y un websocket): solo en ese caso se permiten.
     *
     * @return array{http: list<string>, ws: list<string>}
     */
    private function servidorDeDesarrollo(): array
    {
        $hot = public_path('hot');

        if (app()->isProduction() || ! is_file($hot)) {
            return ['http' => [], 'ws' => []];
        }

        $partes = parse_url(trim((string) file_get_contents($hot)));

        if ($partes === false || ! isset($partes['host'])) {
            return ['http' => [], 'ws' => []];
        }

        $hostPuerto = $partes['host'].(isset($partes['port']) ? ':'.$partes['port'] : '');
        $esquema = $partes['scheme'] ?? 'http';

        return ['http' => ["{$esquema}://{$hostPuerto}"], 'ws' => [($esquema === 'https' ? 'wss' : 'ws')."://{$hostPuerto}"]];
    }
}
