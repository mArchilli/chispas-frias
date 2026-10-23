<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\RolUsuario;
use App\Services\SesionesService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'email_verified_at',
    ];

    // `rukoz_vendedor_id` (el id con el que el usuario se presenta ante el Central) NO está en $fillable: se asigna solo desde el
    // admin de vendedores o con `chispasfrias:vendedor-mapear`, nunca por asignación masiva.

    /**
     * Valores por defecto de atributos en memoria. Sin esto, un modelo recién
     * creado (User::create() sin 'role') queda con role=null hasta que se
     * refresca desde la base. Igual que el default de la columna, es 'cliente':
     * un usuario sin rol explícito nunca puede ser administrador. Admin y
     * vendedor se asignan siempre de forma explícita.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'cliente',
        'is_active' => true,
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => RolUsuario::class,
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Cambió la contraseña, el rol o se desactivó la cuenta: se cierran sus sesiones abiertas. La de quien cambia su PROPIA
        // contraseña se conserva (sigue logueado en el equipo donde lo hizo).
        static::updated(function (User $usuario) {
            $desactivada = $usuario->wasChanged('is_active') && ! $usuario->is_active;

            if (! $usuario->wasChanged(['password', 'role']) && ! $desactivada) {
                return;
            }

            $propia = auth()->id() === $usuario->getKey() && ! $desactivada && ! $usuario->wasChanged('role');

            app(SesionesService::class)->cerrarDe($usuario, $propia && app()->bound('session.store') ? session()->getId() : null);
        });
    }

    public function isAdmin(): bool
    {
        return $this->role === RolUsuario::Admin;
    }

    public function isVendedor(): bool
    {
        return $this->role === RolUsuario::Vendedor;
    }

    public function isCliente(): bool
    {
        return $this->role === RolUsuario::Cliente;
    }

    public function puedeAccederAlPanel(): bool
    {
        return $this->isAdmin() || $this->isVendedor();
    }
}
