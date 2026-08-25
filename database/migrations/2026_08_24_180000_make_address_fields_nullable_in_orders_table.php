<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ya no se envía a domicilio, solo a sucursal: el checkout dejó de pedir
     * dirección y número. Se dejan las columnas (en vez de borrarlas) para no
     * perder los datos de las órdenes ya creadas con envío a domicilio.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('address')->nullable()->change();
            $table->string('number')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('address')->nullable(false)->change();
            $table->string('number')->nullable(false)->change();
        });
    }
};
