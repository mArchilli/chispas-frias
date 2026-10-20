<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marcas de tiempo del uso del código de descuento de cada pedido, para que consumir y liberar
     * sean idempotentes: la primera llamada deja la marca y cualquier repetición (reintento, evento
     * duplicado) no vuelve a tocar el contador del código.
     *
     * Solo agrega columnas nullable: no modifica ninguna fila existente. Los pedidos viejos
     * conservan su `discount_usage_repuesto` (booleano heredado), que sigue contando como
     * "ya liberado".
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('discount_usage_consumido_at')->nullable()->after('discount_usage_repuesto');
            $table->timestamp('discount_usage_liberado_at')->nullable()->after('discount_usage_consumido_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['discount_usage_consumido_at', 'discount_usage_liberado_at']);
        });
    }
};
