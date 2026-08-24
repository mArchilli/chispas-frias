<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RolUsuario;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SellerController extends Controller
{
    /**
     * Listado de cuentas de vendedor. No mezcla clientes ni otros admins:
     * el ABM de esta pantalla es exclusivamente sobre role=vendedor.
     */
    public function index(): Response
    {
        $sellers = User::where('role', RolUsuario::Vendedor)
            ->orderBy('name')
            ->get()
            ->map(fn (User $seller) => $this->transform($seller));

        return Inertia::render('Admin/Sellers/Index', [
            'sellers' => $sellers,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Sellers/Create');
    }

    /**
     * El proyecto no tiene un mailer real configurado (MAIL_MAILER=log sólo
     * escribe en el log, no entrega correos), así que mandar la contraseña
     * temporal por mail no funcionaría hoy. En su lugar se genera acá y se
     * devuelve una única vez en la respuesta de éxito para que el admin se la
     * pase al vendedor a mano; no se persiste en texto plano en ningún lado.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:users,email',
        ]);

        $temporaryPassword = Str::password(12);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($temporaryPassword),
            'role' => RolUsuario::Vendedor,
            // El admin ya dio de alta la cuenta a mano: no tiene sentido mandarle
            // un mail de verificación a una dirección que él mismo confirmó.
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        return redirect()->route('admin.sellers.index')
            ->with('success', 'Vendedor creado exitosamente')
            ->with('temporaryPassword', $temporaryPassword);
    }

    public function edit(User $seller): Response
    {
        $this->ensureIsSeller($seller);

        return Inertia::render('Admin/Sellers/Edit', [
            'seller' => $this->transform($seller),
        ]);
    }

    public function update(Request $request, User $seller): RedirectResponse
    {
        $this->ensureIsSeller($seller);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($seller->id)],
        ]);

        $seller->update($validated);

        return redirect()->route('admin.sellers.index')->with('success', 'Vendedor actualizado exitosamente');
    }

    /**
     * No hay destroy: se reemplaza por activar/desactivar (ver migración
     * add_is_active_to_users_table). Desactivar revoca el acceso de inmediato
     * (LoginRequest::authenticate rechaza el login aunque la contraseña sea
     * correcta) y además cierra cualquier sesión que ya tuviera abierta,
     * sin borrar la cuenta ni el historial de pedidos que gestionó.
     */
    public function toggleStatus(User $seller): RedirectResponse
    {
        $this->ensureIsSeller($seller);

        $seller->update(['is_active' => ! $seller->is_active]);

        if (! $seller->is_active) {
            DB::table('sessions')->where('user_id', $seller->id)->delete();
        }

        $status = $seller->is_active ? 'activado' : 'desactivado';

        return back()->with('success', "Vendedor {$status} exitosamente");
    }

    /**
     * Evita que, vía route model binding, se edite/desactive una cuenta que
     * no sea de vendedor (un admin o un cliente) entrando directo por URL.
     */
    private function ensureIsSeller(User $seller): void
    {
        abort_unless($seller->role === RolUsuario::Vendedor, 404);
    }

    private function transform(User $seller): array
    {
        return [
            'id' => $seller->id,
            'name' => $seller->name,
            'email' => $seller->email,
            'is_active' => $seller->is_active,
            'created_at' => $seller->created_at,
        ];
    }
}
