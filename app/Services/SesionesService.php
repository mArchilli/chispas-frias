<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cierra las sesiones abiertas de un usuario (cambió su contraseña o su rol, o se desactivó la cuenta).
 *
 * Con SESSION_DRIVER=database borra sus filas de `sessions`. Con otro driver no hay forma de listarlas, pero igual quedan cerradas:
 * el middleware AuthenticateSession (grupo web) compara el hash de la contraseña guardado en cada sesión con el actual, y
 * EnsureUserIsActive corta las cuentas desactivadas. Ver docs/SEGURIDAD.md → Sesiones.
 */
class SesionesService
{
    /** @param  string|null  $excepto  id de la sesión que se conserva (la de quien acaba de cambiar su propia contraseña). */
    public function cerrarDe(User $usuario, ?string $excepto = null): void
    {
        if (config('session.driver') !== 'database' || ! Schema::hasTable(config('session.table', 'sessions'))) {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $usuario->getKey())
            ->when($excepto, fn ($consulta) => $consulta->where('id', '!=', $excepto))
            ->delete();
    }
}
