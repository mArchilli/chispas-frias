<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot de "este pedido tiene envío gratis" para el panel de órdenes.
     * Se pone en true cuando el carrito incluye al menos un combo con envío
     * gratis (is_free_shipping). El envío gratis por umbral global sigue siendo
     * informativo dentro del mensaje, no se persiste acá.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('free_shipping')->default(false)->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('free_shipping');
        });
    }
};
