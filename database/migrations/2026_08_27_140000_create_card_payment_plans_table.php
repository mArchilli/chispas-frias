<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogo de planes de pago con tarjeta de crédito. El recargo es 100%
     * informativo: este proyecto no cobra online (coordina todo por WhatsApp),
     * así que estos planes sólo sirven para que el vendedor sepa por qué monto
     * generar el link de pago manual desde su cuenta de Mercado Pago.
     */
    public function up(): void
    {
        Schema::create('card_payment_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->unsignedInteger('installments');
            // Recargo único sobre el total del pedido completo (no interés
            // compuesto cuota a cuota). Ver App\Services\CardSurchargeService.
            $table->decimal('surcharge_percentage', 5, 2);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_payment_plans');
    }
};
