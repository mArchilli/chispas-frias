<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Rukoz\ReferenciasDelCentral;
use App\Rukoz\VendedorParaCentral;
use Illuminate\Console\Command;

/**
 * Mapeo usuario de la web → vendedor del Sistema Central. El «id» es el `vendedor.id` con el que el usuario se presenta
 * al confirmar una venta (`users.rukoz_vendedor_id`); el Central lo asigna a un usuario suyo (Integraciones → Vendedores) y, una
 * vez asignado, figura en `GET /referencias`. Sin mapeo el usuario no puede confirmar ventas.
 *
 * También se edita desde Admin → Vendedores («Vendedor en el Sistema Central»).
 */
class VendedorMapear extends Command
{
    protected $signature = 'chispasfrias:vendedor-mapear
        {usuario? : Email o id del usuario de la web}
        {vendedor? : Id del vendedor con el que se presenta ante el Central (por defecto, el id del usuario)}
        {--quitar : Quita el mapeo del usuario}
        {--listar : Lista los usuarios con su mapeo y los vendedores que el Central ya tiene asignados}';

    protected $description = 'Vincula un usuario de la web con un vendedor del Sistema Central (o lista los vínculos)';

    public function handle(): int
    {
        if ($this->option('listar') || $this->argument('usuario') === null) {
            return $this->listar();
        }

        $usuario = User::query()->where('email', $this->argument('usuario'))->orWhere('id', $this->argument('usuario'))->first();

        if ($usuario === null) {
            $this->components->error('No existe un usuario con ese email o id.');

            return self::FAILURE;
        }

        if ($this->option('quitar')) {
            $usuario->forceFill(['rukoz_vendedor_id' => null])->save();
            $this->components->info("{$usuario->name}: sin vendedor del Central (ya no puede confirmar ventas).");

            return self::SUCCESS;
        }

        $vendedor = trim((string) ($this->argument('vendedor') ?? $usuario->id));

        if ($vendedor === '' || mb_strlen($vendedor) > 100) {
            $this->components->error('El id del vendedor no puede estar vacío ni pasar de 100 caracteres.');

            return self::FAILURE;
        }

        $otro = User::query()->where('rukoz_vendedor_id', $vendedor)->whereKeyNot($usuario->id)->first();

        if ($otro !== null) {
            $this->components->error("El vendedor «{$vendedor}» ya está asignado a {$otro->name}: el Central lo trata como una sola persona.");

            return self::FAILURE;
        }

        $usuario->forceFill(['rukoz_vendedor_id' => $vendedor])->save();
        $this->components->info("{$usuario->name} → vendedor «{$vendedor}» del Sistema Central.");

        $referencias = ReferenciasDelCentral::obtener(forzar: true);
        $habilitado = VendedorParaCentral::habilitadoEnElCentral($usuario, $referencias['origen'] === 'central' ? $referencias['vendedores'] : null);

        if ($habilitado === false) {
            $this->components->warn('El Central todavía no lo tiene asignado a un usuario suyo (Integraciones → Vendedores). El primer intento de confirmar lo deja pendiente allá para que administración lo asigne.');
        } elseif ($habilitado === true) {
            $this->components->info('El Central ya lo tiene asignado: puede confirmar ventas.');
        }

        return self::SUCCESS;
    }

    private function listar(): int
    {
        $referencias = ReferenciasDelCentral::obtener(forzar: true);

        $this->table(
            ['Id', 'Usuario', 'Email', 'Rol', 'Vendedor del Central', 'Asignado en el Central'],
            User::query()->orderBy('name')->get()->map(fn (User $u) => [
                $u->id,
                $u->name,
                $u->email,
                $u->role?->value,
                VendedorParaCentral::mapeado($u) ? $u->rukoz_vendedor_id : '— sin mapeo —',
                match (VendedorParaCentral::habilitadoEnElCentral($u, $referencias['origen'] === 'central' ? $referencias['vendedores'] : null)) {
                    true => 'sí',
                    false => 'NO',
                    null => '?',
                },
            ])->all()
        );

        $this->line('Vendedores que el Central ya tiene asignados: '.($referencias['vendedores'] === []
            ? '(ninguno'.($referencias['error'] ? ' — '.$referencias['error'] : '').')'
            : collect($referencias['vendedores'])->map(fn ($v) => ($v['id'] ?? '?').' '.($v['nombre'] ?? ''))->implode(', ')));

        return self::SUCCESS;
    }
}
