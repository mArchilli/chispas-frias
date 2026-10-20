<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cambia SOLO el valor por defecto de users.role de 'admin' a 'cliente'.
     *
     * El default 'admin' era una red de seguridad de backfill al introducir roles; dejado así, un
     * User::create() sin 'role' (o un INSERT a mano) crea un administrador. No toca ninguna fila
     * existente: los usuarios que ya están conservan el rol que tienen.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('cliente')->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('admin')->change();
        });
    }
};
