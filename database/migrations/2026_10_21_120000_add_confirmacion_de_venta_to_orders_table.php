<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 10.4 — Confirmar la venta en el Central y reflejar sus estados. Solo columnas nuevas y nullable (aditiva): los pedidos
 * existentes quedan con todo en NULL y siguen con el flujo de siempre.
 *
 * orders
 *  - confirmado_at          cuándo se confirmó la venta (la fecha con la que cuenta en las métricas un pedido con `rukoz_ref`).
 *  - confirmando_at         marca fija del primer intento de confirmar: se manda como `confirmado_at` y como `fecha` de los pagos,
 *                           así un reintento tras un timeout lleva EXACTAMENTE los mismos bytes y el Central devuelve la misma venta.
 *  - confirmado_por         usuario de la web que confirmó; NULL si se confirmó desde el Central.
 *  - rukoz_numero_venta     número de la venta en el Central.
 *  - rukoz_total_venta / rukoz_pagado / rukoz_saldo   lo que informa el Central (el total de la venta incluye el envío cobrado).
 *  - rukoz_retenido_por_pago   `retenido_por_pago`: la venta se confirmó con «despachar solo con pago completo» y queda saldo.
 *  - envio_costo_cobrado    costo de envío cobrado al confirmar (`envio.costo_cobrado`).
 *  - rukoz_transportista, rukoz_numero_seguimiento, rukoz_url_seguimiento   seguimiento del envío (lo carga el Central).
 *  - rukoz_empaquetado_at, despachado_at, rukoz_retirado_at, rukoz_anulado_at   fechas de los estados de la venta.
 *
 * users
 *  - rukoz_vendedor_id      id con el que este usuario se presenta ante el Central como vendedor (`vendedor.id`). NULL = sin mapeo:
 *                           no puede confirmar ventas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('confirmado_at')->nullable()->index()->after('motivo_cancelacion');
            $table->timestamp('confirmando_at')->nullable()->after('confirmado_at');
            $table->unsignedBigInteger('confirmado_por')->nullable()->after('confirmando_at');
            $table->string('rukoz_numero_venta', 60)->nullable()->after('confirmado_por');
            $table->decimal('rukoz_total_venta', 12, 2)->nullable()->after('rukoz_numero_venta');
            $table->decimal('rukoz_pagado', 12, 2)->nullable()->after('rukoz_total_venta');
            $table->decimal('rukoz_saldo', 12, 2)->nullable()->after('rukoz_pagado');
            $table->boolean('rukoz_retenido_por_pago')->nullable()->after('rukoz_saldo');
            $table->decimal('envio_costo_cobrado', 12, 2)->nullable()->after('rukoz_retenido_por_pago');
            $table->string('rukoz_transportista', 100)->nullable()->after('envio_costo_cobrado');
            $table->string('rukoz_numero_seguimiento', 100)->nullable()->after('rukoz_transportista');
            $table->string('rukoz_url_seguimiento', 500)->nullable()->after('rukoz_numero_seguimiento');
            $table->timestamp('rukoz_empaquetado_at')->nullable()->after('rukoz_url_seguimiento');
            $table->timestamp('despachado_at')->nullable()->after('rukoz_empaquetado_at');
            $table->timestamp('rukoz_retirado_at')->nullable()->after('despachado_at');
            $table->timestamp('rukoz_anulado_at')->nullable()->after('rukoz_retirado_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('rukoz_vendedor_id', 100)->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('rukoz_vendedor_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['confirmado_at']);
            $table->dropColumn([
                'confirmado_at', 'confirmando_at', 'confirmado_por', 'rukoz_numero_venta', 'rukoz_total_venta', 'rukoz_pagado',
                'rukoz_saldo', 'rukoz_retenido_por_pago', 'envio_costo_cobrado', 'rukoz_transportista', 'rukoz_numero_seguimiento',
                'rukoz_url_seguimiento', 'rukoz_empaquetado_at', 'despachado_at', 'rukoz_retirado_at', 'rukoz_anulado_at',
            ]);
        });
    }
};
