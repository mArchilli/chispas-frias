<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\RolUsuario;
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

    /**
     * Valores por defecto de atributos en memoria. Sin esto, un modelo recién
     * creado (User::create() sin 'role') queda con role=null hasta que se
     * refresca desde la base, aunque la columna ya tenga el default 'admin'.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'admin',
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
