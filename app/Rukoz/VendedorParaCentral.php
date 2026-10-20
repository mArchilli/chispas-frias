<?php

namespace App\Rukoz;

use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * ADAPTADOR del vendedor de una venta (10.4). Es el único lugar que sabe cómo se presenta un usuario de la web ante el Central.
 *
 * Contrato (Central 0.1.1, `POST /pedidos/{ref}/confirmar`): `vendedor` es obligatorio y lleva `id` (el id del vendedor EN LA WEB,
 * texto), más `email` y `nombre` opcionales. El Central asigna ese `id` a un usuario suyo (pestaña Vendedores de la integración);
 * si todavía no está asignado contesta `422 VENDEDOR_NO_MAPEADO` y deja al vendedor pendiente en su panel. Las asignaciones
 * hechas figuran en `GET /referencias` → `vendedores[].id`.
 *
 * El «mapeo» de la web es `users.rukoz_vendedor_id`: el `vendedor.id` con el que cada usuario se presenta. Sin ese valor el
 * usuario no puede confirmar ventas (la web ni siquiera llama al Central). Si algún día el Central pide otro formato (por ejemplo
 * el código de un usuario suyo en vez de un id de la web) se cambia SOLO `payload()`.
 */
final class VendedorParaCentral
{
    public static function mapeado(User $usuario): bool
    {
        return trim((string) $usuario->rukoz_vendedor_id) !== '';
    }

    /**
     * El `vendedor` del body de `confirmar`.
     *
     * @return array{id: string, email: string, nombre: string}
     *
     * @throws ValidationException Si el usuario no tiene mapeo.
     */
    public static function payload(User $usuario): array
    {
        if (! self::mapeado($usuario)) {
            throw ValidationException::withMessages(['confirmacion' => self::mensajeSinMapeo($usuario)]);
        }

        return [
            'id' => trim((string) $usuario->rukoz_vendedor_id),
            'email' => (string) $usuario->email,
            'nombre' => (string) $usuario->name,
        ];
    }

    public static function mensajeSinMapeo(User $usuario): string
    {
        return "{$usuario->name} no tiene asignado un vendedor del Sistema Central, así que no puede confirmar ventas. "
            .'Un administrador lo vincula en Vendedores (campo «Vendedor en el Sistema Central») o con '
            .'«php artisan chispasfrias:vendedor-mapear».';
    }

    /**
     * ¿El Central ya lo tiene asignado? `null` = no se sabe (no hay lista de vendedores). Solo informativo: el que decide es el
     * Central al confirmar.
     *
     * @param  list<array<string, mixed>>|null  $vendedoresDelCentral  `vendedores` de `GET /referencias`; `null` si el Central no contestó (no se sabe). Una lista vacía es una respuesta: ninguno asignado.
     */
    public static function habilitadoEnElCentral(User $usuario, ?array $vendedoresDelCentral): ?bool
    {
        if (! self::mapeado($usuario) || $vendedoresDelCentral === null) {
            return null;
        }

        $id = trim((string) $usuario->rukoz_vendedor_id);

        foreach ($vendedoresDelCentral as $vendedor) {
            if ((string) ($vendedor['id'] ?? '') === $id || (string) ($vendedor['external_id'] ?? '') === $id) {
                return true;
            }
        }

        return false;
    }
}
