<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierra la sesión de admin y vendedores tras N minutos sin actividad (config/seguridad.php → admin_inactividad_minutos, 0 = apagado).
 * Va en las rutas /admin: la sesión de un cliente (que no tiene nada que hacer ahí) y el carrito público no se tocan.
 * Cada request al panel renueva el contador.
 */
class CerrarSesionPorInactividad
{
    public const CLAVE = 'admin_ultima_actividad';

    public function handle(Request $request, Closure $next): Response
    {
        $minutos = (int) config('seguridad.admin_inactividad_minutos');

        if ($minutos > 0 && $request->user()) {
            $ultima = (int) $request->session()->get(self::CLAVE, 0);

            if ($ultima > 0 && (time() - $ultima) > $minutos * 60) {
                Auth::guard('web')->logout();

                $request->session()->invalidate();
                $request->session()->regenerateToken();

                if ($request->expectsJson()) {
                    return response()->json(['message' => 'La sesión venció por inactividad.'], 401);
                }

                return redirect()->route('login')->with('status', 'Tu sesión se cerró por inactividad. Ingresá de nuevo.');
            }

            $request->session()->put(self::CLAVE, time());
        }

        return $next($request);
    }
}
