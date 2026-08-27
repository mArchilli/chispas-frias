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
        Schema::table('product_images', function (Blueprint $table) {
            // Asocia el medio (aplica igual a type=image y type=video) a una
            // variante puntual. null = medio "general", se muestra para
            // cualquier color. nullOnDelete: si se borra la variante, el medio
            // pasa a ser general en vez de desaparecer.
            $table->foreignId('product_variant_id')->nullable()->after('product_id')
                ->constrained('product_variants')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_variant_id');
        });
    }
};
