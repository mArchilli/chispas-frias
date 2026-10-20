<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 10.3 — El pedido reserva el stock en el Sistema Central. Solo columnas nuevas y nullable: los pedidos
 * existentes quedan con todo en NULL y siguen con el flujo de siempre (stock local).
 *
 *  - rukoz_ref           referencia externa del pedido en el Central (`external_ref`). Se genera ANTES de reservar y
 *                        es la clave de idempotencia. NULL = pedido de la web sola (stock local).
 *  - rukoz_version       versión del pedido en el Central al guardarlo (orden de eventos, 10.4).
 *  - rukoz_estado        estado de la reserva: reservado | cancelado | cancelacion_pendiente (la cancelación está en la
 *                        cola de compensaciones del paquete: el pedido local ya está cancelado, el Central todavía no).
 *  - motivo_cancelacion  por qué se canceló (también se manda al Central).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('rukoz_ref', 100)->nullable()->unique()->after('estado');
            $table->unsignedInteger('rukoz_version')->nullable()->after('rukoz_ref');
            $table->string('rukoz_estado', 30)->nullable()->index()->after('rukoz_version');
            $table->string('motivo_cancelacion', 500)->nullable()->after('rukoz_estado');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['rukoz_ref']);
            $table->dropIndex(['rukoz_estado']);
            $table->dropColumn(['rukoz_ref', 'rukoz_version', 'rukoz_estado', 'motivo_cancelacion']);
        });
    }
};
