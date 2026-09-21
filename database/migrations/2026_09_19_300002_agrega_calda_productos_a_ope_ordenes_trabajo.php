<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pedido del dueño del 19/9/2026: la calda de una Orden de Trabajo se registra
 * con casillas simples —Glifosato, 2,4-D, Agua, Urea—, sin cantidades. Como
 * `Mezclas` exige cantidad y unidad por producto, esas casillas no pueden ir
 * por ahí: quedan en la cabecera de la tanda, como lista de valores de
 * `Operaciones\Dominio\ProductoCalda`.
 *
 * Nula en las tandas anteriores a esta migración y en las que no marcaron
 * ninguna casilla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ope_ordenes_trabajo', function (Blueprint $table) {
            $table->jsonb('calda_productos')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ope_ordenes_trabajo', function (Blueprint $table) {
            $table->dropColumn('calda_productos');
        });
    }
};
