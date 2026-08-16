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
        Schema::table('product_offers', function (Blueprint $table) {
            // tipo_descuento y valor_descuento nullable: se completan en el backfill
            // a partir de offer_price (ver comando offers:backfill-discount-fields).
            $table->string('tipo_descuento')->nullable()->after('percentage_discount');
            $table->decimal('valor_descuento', 10, 2)->nullable()->after('tipo_descuento');
            $table->string('alcance')->default('todos')->after('valor_descuento');
            $table->foreignId('product_price_tier_id')
                ->nullable()
                ->after('alcance')
                ->constrained('product_price_tiers')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_offers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_price_tier_id');
            $table->dropColumn(['tipo_descuento', 'valor_descuento', 'alcance']);
        });
    }
};
