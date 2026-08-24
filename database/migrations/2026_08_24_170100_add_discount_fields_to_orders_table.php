<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('discount_code_id')->nullable()->after('user_id')
                ->constrained('discount_codes')->onDelete('set null');

            // Snapshot del texto del código al momento de la orden (por si el
            // código se borra o cambia después), igual que order_items guarda
            // product_title como copia.
            $table->string('discount_code')->nullable()->after('discount_code_id');

            // Total de productos antes de aplicar el descuento del código.
            $table->decimal('subtotal', 10, 2)->nullable()->after('discount_code');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('subtotal');

            // Marca si esta orden ya repuso el uso de su código de descuento
            // (p. ej. al cancelarla), para que DiscountCodeService::reponerUso()
            // no se llame dos veces sobre la misma orden.
            $table->boolean('discount_usage_repuesto')->default(false)->after('discount_amount');
        });

        // Backfill: hoy total = subtotal porque no existían códigos de descuento.
        DB::table('orders')->update(['subtotal' => DB::raw('total')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('discount_code_id');
            $table->dropColumn(['discount_code', 'subtotal', 'discount_amount', 'discount_usage_repuesto']);
        });
    }
};
