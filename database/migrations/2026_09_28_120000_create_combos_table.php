<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Combos: paquetes de varios productos que se venden a un precio FIJO
     * definido por el admin, independiente del precio de los productos que los
     * integran (ver combo_items). El precio no se deriva de los componentes ni
     * de sus tiers/ofertas.
     */
    public function up(): void
    {
        Schema::create('combos', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();

            // Precio fijo del combo. No depende de los componentes.
            $table->decimal('price', 10, 2);

            // Envío gratis propio del combo: si está activo, el pedido que lo
            // incluye califica para envío gratis sin importar el umbral global
            // (free_shipping_threshold). Se aclara en la ficha, el carrito y el
            // mensaje de WhatsApp del checkout.
            $table->boolean('is_free_shipping')->default(false);

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('combos');
    }
};
