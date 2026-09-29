<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una línea de orden puede ser un producto (como hasta ahora) o un combo.
     * En una línea de combo: product_id / product_variant_id quedan null,
     * product_title guarda el nombre del combo, precio_unitario/base_unit_price
     * el precio fijo del combo, y combo_selections snapshotea qué productos y
     * colores eligió el cliente (para el historial y para descontar/reponer
     * stock por componente). Mismo espíritu que addons_selected.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('combo_id')->nullable()->after('product_id')
                ->constrained('combos')->nullOnDelete();

            // Snapshot de los componentes elegidos: array de
            // {product_id, product_variant_id, product_title, variant_name,
            //  variant_color_hex, custom_color_text, quantity}.
            $table->json('combo_selections')->nullable()->after('addons_selected');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('combo_id');
            $table->dropColumn('combo_selections');
        });
    }
};
