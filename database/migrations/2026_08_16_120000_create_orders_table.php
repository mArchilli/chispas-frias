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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');

            // Datos de contacto (mismos campos y reglas que CartController::generateWhatsAppMessage)
            $table->string('name');
            $table->string('lastname');
            $table->string('dni');
            $table->string('province');
            $table->string('city')->nullable();
            $table->string('address');
            $table->string('number');
            $table->string('between_streets')->nullable();
            $table->string('postal_code');
            $table->string('phone');
            $table->string('email');
            $table->text('observations')->nullable();

            $table->string('estado')->default('pendiente');
            $table->decimal('total', 10, 2);
            $table->text('mensaje_whatsapp')->nullable();
            $table->timestamps();

            $table->index('estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
