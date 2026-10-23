<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Si la cuenta con la sesión abierta fue desactivada (`users.is_active = false`), se le cierra la sesión en el próximo request.
 *
 * El login ya rechaza las cuentas desactivadas (LoginRequest) y SellerController::toggleStatus borra sus filas de `sessions`, pero eso
 * solo funciona con SESSION_DRIVER=database; este middleware cubre cualquier driver (y una desactivación hecha por otra vía).
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Esta cuenta fue desactivada.'], 401);
            }

            return redirect()->route('login')->withErrors(['email' => 'Esta cuenta fue desactivada. Contactá a un administrador.']);
        }

        return $next($request);
    }
}
