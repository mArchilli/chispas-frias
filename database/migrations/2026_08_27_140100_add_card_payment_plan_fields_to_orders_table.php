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
        Schema::table('orders', function (Blueprint $table) {
            // nullOnDelete: mismo patrón que discount_code_id — si el plan se
            // borra, la orden no debe desaparecer ni fallar, sólo pierde el
            // vínculo (el snapshot de abajo es la fuente de verdad histórica).
            $table->foreignId('card_payment_plan_id')->nullable()->after('total')
                ->constrained('card_payment_plans')->nullOnDelete();

            // Snapshot del plan tal como se usó, igual que discount_code guarda
            // el texto del código: no depende de que el CardPaymentPlan siga
            // existiendo o sin cambios para reconstruir la orden histórica.
            $table->string('payment_plan_name')->nullable()->after('card_payment_plan_id');
            $table->unsignedInteger('payment_plan_installments')->nullable()->after('payment_plan_name');
            $table->decimal('surcharge_percentage', 5, 2)->nullable()->after('payment_plan_installments');
            $table->decimal('surcharge_amount', 10, 2)->nullable()->after('surcharge_percentage');

            // Total real a pagar con tarjeta (total + surcharge_amount). Sin plan
            // elegido, estos campos quedan null y la orden sigue usando la
            // columna total existente exactamente como hoy.
            $table->decimal('total_with_surcharge', 10, 2)->nullable()->after('surcharge_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('card_payment_plan_id');
            $table->dropColumn([
                'payment_plan_name',
                'payment_plan_installments',
                'surcharge_percentage',
                'surcharge_amount',
                'total_with_surcharge',
            ]);
        });
    }
};
