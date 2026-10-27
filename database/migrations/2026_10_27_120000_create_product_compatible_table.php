<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pares de productos compatibles entre sí (p. ej. una pistola y la chispa fría que usa). La relación es simétrica: cada
     * par se guarda UNA sola vez y se consulta en los dos sentidos (ver Product::idsCompatibles). Con ella el carrito sugiere lo
     * que funciona con lo que el cliente ya lleva. Migración aditiva: no toca ninguna tabla existente salvo agregar una columna.
     */
    public function up(): void
    {
        Schema::create('product_compatible', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('compatible_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);

            $table->unique(['product_id', 'compatible_id']);
        });

        // «Sugerir siempre»: el producto se ofrece en el carrito aunque ningún producto del carrito sea compatible con él.
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('sugerir_en_carrito')->default(false)->after('is_featured');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('sugerir_en_carrito');
        });

        Schema::dropIfExists('product_compatible');
    }
};
