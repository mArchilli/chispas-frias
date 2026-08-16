<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * offer_price pasa a ser nullable: a partir de la Fase C4, una oferta con
     * alcance=Especifico apuntando a un tier que no es el precio base no
     * afecta ese precio, así que su espejo offer_price queda en null (ver
     * SyncsOfferDiscountFields::applyOfferDiscountMirror).
     */
    public function up(): void
    {
        Schema::table('product_offers', function (Blueprint $table) {
            $table->decimal('offer_price', 10, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_offers', function (Blueprint $table) {
            $table->decimal('offer_price', 10, 2)->nullable(false)->change();
        });
    }
};
