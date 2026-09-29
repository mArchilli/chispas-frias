<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Galería del combo (imágenes y videos), espejo de product_images pero sin
     * la parte de variantes. Los archivos se guardan en la MISMA carpeta física
     * que los de productos (PRODUCT_IMAGES_PATH); el nombre de archivo es único.
     */
    public function up(): void
    {
        Schema::create('combo_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('combo_id')->constrained('combos')->cascadeOnDelete();

            $table->string('path');
            $table->string('alt_text')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);

            // 'image' | 'video', mismo criterio que product_images.type.
            $table->string('type')->default('image');
            $table->string('mime_type')->nullable();
            $table->timestamps();

            $table->index('combo_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('combo_images');
    }
};
