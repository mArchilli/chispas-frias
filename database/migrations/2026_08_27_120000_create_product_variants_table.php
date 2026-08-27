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
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');

            $table->string('name', 100);

            // Color real de la variante. Si is_custom_color = true, color_hex es
            // solo un ícono de referencia en el admin, no el color que se entrega.
            $table->string('color_hex', 7)->nullable();

            // Como mucho una variante por producto puede marcarse "a elección del
            // cliente": el cliente elige libremente el color en vez de uno fijo.
            $table->boolean('is_custom_color')->default(false);

            // Recargo propio de la variante, se suma al precio base/tier.
            $table->decimal('price_addon', 10, 2)->default(0);

            // null = ilimitado, mismo criterio que products.stock.
            $table->integer('stock')->nullable();

            $table->string('sku')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
