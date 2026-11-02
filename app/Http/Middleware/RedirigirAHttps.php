<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En producción, todo pedido que llegue por http:// se redirige (301) a la misma URL en https:// (mismo host, path y query).
 *
 * Con APP_URL https en producción, la app genera todas sus URLs con https (ver AppServiceProvider: URL::forceScheme). Quien entra por
 * http:// recibiría una página cuyos pedidos de Inertia/Ziggy van a otro origen (https) y quedaría rota; por eso se lo manda a https
 * antes de que la página exista. Va como middleware GLOBAL, antes del grupo `web`: así un pedido http no llega a la sesión ni crea
 * cookies.
 *
 * No toca: /csp-report (el navegador lo manda a la URL de la página que lo originó), /.well-known/* (renovación del certificado por
 * http) ni /up (chequeo de salud). Fuera de producción no hace nada (desarrollo local, tests). SEGURIDAD_FORZAR_HTTPS=false lo apaga
 * sin tocar código (por si algún proxy hiciera que la app vea http y se generara un bucle).
 *
 * GET y HEAD reciben 301; el resto de los métodos recibe 308, que conserva el método y el cuerpo (un 301 los convertiría en GET).
 */
class RedirigirAHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->isProduction()
            || ! config('seguridad.forzar_https', true)
            || $request->isSecure()
            || $request->is('csp-report', '.well-known/*', 'up')) {
            return $next($request);
        }

        $destino = 'https://'.$request->getHost().$request->getRequestUri();

        return redirect()->away($destino, in_array($request->getMethod(), ['GET', 'HEAD'], true) ? 301 : 308);
    }
}
