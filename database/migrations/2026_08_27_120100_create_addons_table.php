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
        // Catálogo GLOBAL de add-ons de personalización, reutilizable entre
        // productos. El precio puede sobreescribirse por producto en el pivote
        // product_addon (price_override).
        Schema::create('addons', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);

            // Si requires_text = true, el cliente debe escribir un texto de
            // personalización (ej. nombre a grabar); text_placeholder y
            // max_characters gobiernan ese input.
            $table->boolean('requires_text')->default(false);
            $table->string('text_placeholder')->nullable();
            $table->unsignedInteger('max_characters')->nullable()->default(40);

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addons');
    }
};
