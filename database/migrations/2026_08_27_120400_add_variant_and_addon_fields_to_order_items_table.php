<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Variante elegida (color). nullOnDelete para no romper el historial
            // si se borra la variante; los campos *_name / *_color_hex /
            // *_price_addon son snapshots que sobreviven a ese borrado.
            $table->foreignId('product_variant_id')->nullable()->after('product_id')
                ->constrained('product_variants')->onDelete('set null');
            $table->string('variant_name')->nullable()->after('product_title');
            $table->string('variant_color_hex')->nullable()->after('variant_name');
            $table->decimal('variant_price_addon', 10, 2)->default(0)->after('variant_color_hex');

            // Solo cuando la variante es is_custom_color: color libre pedido por
            // el cliente.
            $table->string('custom_color_text')->nullable()->after('variant_price_addon');

            // Snapshot de los add-ons elegidos: array de
            // {addon_id, name, price, custom_text}.
            $table->json('addons_selected')->nullable()->after('custom_color_text');
            $table->decimal('addons_total', 10, 2)->default(0)->after('addons_selected');

            // Precio sin recargos (base/tier con oferta aplicada), para reportes.
            // precio_unitario NO cambia de significado: sigue siendo el final con
            // recargo de variante y add-ons incluidos.
            $table->decimal('base_unit_price', 10, 2)->nullable()->after('precio_unitario');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_variant_id');
            $table->dropColumn([
                'variant_name',
                'variant_color_hex',
                'variant_price_addon',
                'custom_color_text',
                'addons_selected',
                'addons_total',
                'base_unit_price',
            ]);
        });
    }
};
