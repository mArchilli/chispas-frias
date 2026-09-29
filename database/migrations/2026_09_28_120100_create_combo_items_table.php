<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Productos que integran un combo, con la cantidad de cada uno. El cliente
     * elige el color (variante) de cada componente al comprar; esas variantes
     * las aporta el propio producto (product_variants), no se fijan acá.
     */
    public function up(): void
    {
        Schema::create('combo_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('combo_id')->constrained('combos')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();

            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            // Un producto aparece a lo sumo una vez por combo (la cantidad va en
            // quantity), mismo criterio que product_variants.name por producto.
            $table->unique(['combo_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('combo_items');
    }
};
