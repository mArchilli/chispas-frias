<?php

namespace Database\Seeders;

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Crea el administrador inicial de un entorno NUEVO (desarrollo). Nunca corre en producción y
 * nunca modifica a un usuario que ya existe.
 *
 * El email sale de SEED_ADMIN_EMAIL; la contraseña de SEED_ADMIN_PASSWORD. Si no hay contraseña
 * se genera una aleatoria y se muestra una sola vez por consola.
 */
class UserSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('UserSeeder no se puede ejecutar en producción.');
        }

        $email = trim((string) config('seeders.admin_email'));

        if ($email === '') {
            throw new RuntimeException('Definí SEED_ADMIN_EMAIL en el .env para crear el administrador inicial.');
        }

        if (User::where('email', $email)->exists()) {
            $this->command?->warn("Ya existe un usuario con el email {$email}: no se modifica.");

            return;
        }

        $password = (string) config('seeders.admin_password');
        $generada = $password === '';

        if ($generada) {
            $password = Str::password(16, symbols: false);
        }

        User::create([
            'name' => config('seeders.admin_nombre'),
            'email' => $email,
            'password' => Hash::make($password),
            'role' => RolUsuario::Admin,
        ]);

        if ($generada) {
            $this->command?->warn("Administrador creado: {$email} — contraseña generada (se muestra una sola vez): {$password}");
        } else {
            $this->command?->info("Administrador creado: {$email}");
        }
    }
}
