<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use App\Enums\RolUsuario;
use App\Models\Order;
use App\Models\User;
use App\Rukoz\ConfirmacionDeVenta;
use App\Rukoz\PedidoParaCentral;
use App\Rukoz\ReferenciasDelCentral;
use App\Rukoz\VendedorParaCentral;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * 10.4 — «Confirmar venta» del admin: crea la venta en el Sistema Central a partir de un pedido reservado (con o sin pago).
 * Admin y vendedor. La lógica vive en `App\Rukoz\ConfirmacionDeVenta`.
 */
class OrderConfirmationController extends Controller
{
    /** Lo que necesita el modal: resumen con notas, cuentas, medios, formas de envío y el vendedor. Se pide al abrirlo. */
    public function datos(Request $request, Order $order): JsonResponse
    {
        if (! $order->puedeConfirmarse()) {
            return response()->json(['message' => 'Este pedido no se puede confirmar en su estado actual.'], 409);
        }

        $referencias = ReferenciasDelCentral::obtener();
        $usuario = $request->user();

        $order->load('items');

        return response()->json([
            'referencias' => $referencias,
            'resumen' => [
                'total' => (float) $order->total,
                'subtotal' => (float) $order->subtotal,
                'descuento' => (float) ($order->discount_amount ?? 0),
                'cupon' => $order->discount_code,
                'envio_gratis' => (bool) $order->free_shipping,
                'items' => $order->items->map(fn ($item) => [
                    'titulo' => $item->product_title.($item->variant_name ? ' ('.$item->variant_name.')' : ''),
                    'cantidad' => (int) $item->cantidad,
                    'subtotal' => (float) $item->subtotal,
                    'nota' => PedidoParaCentral::notaDe($item),
                ])->values(),
            ],
            'vendedores' => $this->vendedoresElegibles($usuario, $referencias['origen'] === 'central' ? $referencias['vendedores'] : null),
            'vendedor_por_defecto' => $usuario->id,
            'puede_elegir_vendedor' => $usuario->isAdmin(),
            'preseleccion' => [
                'forma_envio_id' => config('integracion.forma_envio_id'),
                'transportista_id' => config('integracion.transportista_id'),
            ],
        ]);
    }

    public function confirmar(Request $request, Order $order, ConfirmacionDeVenta $confirmacion): RedirectResponse
    {
        $validated = $request->validate([
            'vendedor_id' => ['nullable', 'integer', 'exists:users,id'],
            'pagos' => ['nullable', 'array', 'max:20'],
            'pagos.*.cuenta' => ['required', 'string', 'max:60'],
            'pagos.*.medio' => ['nullable', 'string', 'max:30'],
            'pagos.*.monto' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'envio' => ['required', 'array'],
            'envio.forma_envio_id' => ['required', 'integer'],
            'envio.costo_cobrado' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'envio.es_gratis' => ['nullable', 'boolean'],
            'envio.transportista_id' => ['nullable', 'integer'],
            'solo_con_pago_completo' => ['nullable', 'boolean'],
        ], [
            'pagos.*.cuenta.required' => 'Elegí la cuenta de cada pago.',
            'pagos.*.monto.required' => 'Ingresá el monto de cada pago.',
            'pagos.*.monto.gt' => 'El monto de cada pago tiene que ser mayor a cero.',
            'envio.forma_envio_id.required' => 'Elegí la forma de envío.',
        ]);

        $vendedor = $this->vendedorElegido($request, $validated['vendedor_id'] ?? null);

        $this->validarContraReferencias($validated);

        ['pedido' => $confirmado, 'ya_confirmado' => $yaConfirmado] = $confirmacion->confirmar(
            $order,
            $vendedor,
            [
                'pagos' => $validated['pagos'] ?? [],
                'envio' => $validated['envio'],
                'solo_con_pago_completo' => (bool) ($validated['solo_con_pago_completo'] ?? false),
            ],
            $request->user(),
        );

        $mensaje = $yaConfirmado
            ? 'Este pedido ya estaba confirmado: no se creó otra venta.'
            : 'Venta confirmada en el Sistema Central'.($confirmado->rukoz_numero_venta ? " (N° {$confirmado->rukoz_numero_venta})" : '').'.'
                .($confirmado->rukoz_saldo !== null && (float) $confirmado->rukoz_saldo > 0.004
                    ? ' Queda con saldo $'.number_format((float) $confirmado->rukoz_saldo, 2, ',', '.').' en el Central.'
                    : '');

        return redirect()->route('admin.orders.show', $order)->with('success', $mensaje);
    }

    /** El vendedor de la venta: el usuario logueado, salvo que un admin elija otro. */
    private function vendedorElegido(Request $request, mixed $id): User
    {
        $usuario = $request->user();

        if ($id === null || (int) $id === $usuario->id) {
            return $usuario;
        }

        if (! $usuario->isAdmin()) {
            throw ValidationException::withMessages(['confirmacion' => 'Solo un administrador puede confirmar a nombre de otro vendedor.']);
        }

        return User::findOrFail((int) $id);
    }

    /**
     * Si tenemos las listas del Central, se avisa acá (antes de llamarlo) de una cuenta o forma de envío que no existe. Sin
     * listas no se valida: lo hace el Central y devuelve el detalle.
     *
     * @param  array<string, mixed>  $validated
     */
    private function validarContraReferencias(array $validated): void
    {
        $referencias = ReferenciasDelCentral::obtener();
        $errores = [];

        $cuentas = array_column($referencias['cuentas'], 'codigo');

        if ($cuentas !== []) {
            foreach ($validated['pagos'] ?? [] as $i => $pago) {
                if (! in_array($pago['cuenta'], $cuentas, true)) {
                    $errores["pagos.{$i}.cuenta"] = 'La cuenta elegida no existe en el Sistema Central.';
                }
            }
        }

        $formas = collect($referencias['formas_envio'])->keyBy('id');

        if ($formas->isNotEmpty()) {
            $forma = $formas->get((int) $validated['envio']['forma_envio_id']);

            if ($forma === null) {
                $errores['envio.forma_envio_id'] = 'La forma de envío elegida no existe en el Sistema Central.';
            } elseif (($forma['es_retiro'] ?? false) === true) {
                if ((float) ($validated['envio']['costo_cobrado'] ?? 0) > 0 || ($validated['envio']['es_gratis'] ?? false)) {
                    $errores['envio.costo_cobrado'] = 'Un retiro o entrega en mano no lleva costo de envío ni envío gratis.';
                }
                if (! empty($validated['envio']['transportista_id'])) {
                    $errores['envio.transportista_id'] = 'Un retiro no lleva transportista.';
                }
            }
        }

        if (($validated['envio']['es_gratis'] ?? false) && (float) ($validated['envio']['costo_cobrado'] ?? 0) > 0) {
            $errores['envio.costo_cobrado'] = 'Un envío gratis no puede tener costo cobrado.';
        }

        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }
    }

    /**
     * Para el selector del admin: usuarios con vendedor asignado (más el propio aunque no lo tenga, para explicarlo).
     *
     * @param  list<array<string, mixed>>|null  $vendedoresDelCentral  `null` si no se pudo leer la lista del Central
     * @return list<array<string, mixed>>
     */
    private function vendedoresElegibles(User $usuario, ?array $vendedoresDelCentral): array
    {
        $usuarios = $usuario->isAdmin()
            ? User::query()->orderBy('name')->get()->filter(fn (User $u) => $u->is($usuario) || VendedorParaCentral::mapeado($u))
            : collect([$usuario]);

        return $usuarios->map(fn (User $u) => [
            'id' => $u->id,
            'nombre' => $u->name,
            'rol' => $u->role instanceof RolUsuario ? ucfirst($u->role->value) : null,
            'mapeado' => VendedorParaCentral::mapeado($u),
            'habilitado_en_central' => VendedorParaCentral::habilitadoEnElCentral($u, $vendedoresDelCentral),
            'mensaje' => VendedorParaCentral::mapeado($u) ? null : VendedorParaCentral::mensajeSinMapeo($u),
        ])->values()->all();
    }
}
