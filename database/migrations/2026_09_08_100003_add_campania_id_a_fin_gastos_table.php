<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `ALTER TABLE fin_gastos ADD campania_id` (ADR 0015 punto 6, tarea 69):
 * atribución de COSTO, no de cobro — "en qué campaña se consumió" el gasto,
 * nunca lo que se le factura al cliente (paga por hectárea aplicada, no
 * combustible ni viáticos). FK real + entero plano (ADR 0003 regla 3).
 *
 * Nullable, y sin migración de datos: un gasto interno puro (mantenimiento
 * de la camioneta, un repuesto de galpón) no se atribuye a ninguna campaña,
 * y los gastos que ya existen no tienen forma de inferir a cuál pertenecen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fin_gastos', function (Blueprint $table) {
            $table->unsignedBigInteger('campania_id')->nullable()->after('trabajo_id');
            $table->foreign('campania_id')->references('id')->on('cpn_campanias')->restrictOnDelete();
            $table->index('campania_id');
        });
    }

    public function down(): void
    {
        Schema::table('fin_gastos', function (Blueprint $table) {
            $table->dropForeign(['campania_id']);
            $table->dropColumn('campania_id');
        });
    }
};
