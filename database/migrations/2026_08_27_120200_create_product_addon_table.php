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
        // Pivote entre products y el catálogo global de addons. Un producto
        // "ofrece" un add-on al agregarlo acá; price_override permite un precio
        // propio para ese producto (null = usa addons.price).
        Schema::create('product_addon', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('addon_id')->constrained('addons')->onDelete('cascade');

            $table->decimal('price_override', 10, 2)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'addon_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_addon');
    }
};
