<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Checkout mínimo: el pedido ya no pide DNI, ciudad, código postal ni email, y el apellido ya no se separa del nombre
     * (un solo campo «Nombre y apellido» que se guarda en `name`). Un retiro en oficina tampoco lleva provincia. Esos datos se
     * completan después, en la ficha del pedido o al confirmar la venta.
     *
     * `tipo_entrega` (`envio` | `retiro`) es la única columna nueva; nullable y sin backfill: los pedidos existentes quedan en NULL y se
     * tratan como envío. Las columnas que pasan a nullable conservan los datos que ya tienen (`city` ya lo era).
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('tipo_entrega', 20)->nullable()->after('estado');
            $table->string('lastname')->nullable()->change();
            $table->string('dni')->nullable()->change();
            $table->string('province')->nullable()->change();
            $table->string('postal_code')->nullable()->change();
            $table->string('email')->nullable()->change();
        });
    }

    /**
     * Volver a NOT NULL solo es posible si no hay filas con esos datos vacíos: se completan con '' antes (los pedidos del checkout
     * mínimo no los tienen).
     */
    public function down(): void
    {
        foreach (['lastname', 'dni', 'province', 'postal_code', 'email'] as $columna) {
            DB::table('orders')->whereNull($columna)->update([$columna => '']);
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->string('lastname')->nullable(false)->change();
            $table->string('dni')->nullable(false)->change();
            $table->string('province')->nullable(false)->change();
            $table->string('postal_code')->nullable(false)->change();
            $table->string('email')->nullable(false)->change();
            $table->dropColumn('tipo_entrega');
        });
    }
};
