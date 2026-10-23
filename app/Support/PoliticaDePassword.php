<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Validation\Rules\Password;

/**
 * Qué se le exige a una contraseña nueva según quién es.
 *
 *  - Admin y vendedor (acceden al panel con pedidos y datos de clientes): mínimo configurable (10 por defecto), con mayúsculas,
 *    minúsculas y números. config/seguridad.php → password_staff_minimo.
 *  - Cliente: la regla de siempre (`Password::defaults()`, 8 caracteres), para no cambiar lo que ve quien se registra.
 */
class PoliticaDePassword
{
    public static function para(?User $usuario): Password
    {
        if ($usuario?->puedeAccederAlPanel()) {
            return Password::min(max(8, (int) config('seguridad.password_staff_minimo')))->letters()->mixedCase()->numbers();
        }

        return Password::defaults();
    }
}
