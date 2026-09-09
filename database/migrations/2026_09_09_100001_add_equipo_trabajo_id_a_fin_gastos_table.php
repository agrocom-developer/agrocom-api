<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `ALTER TABLE fin_gastos ADD equipo_trabajo_id` (tarea 73, HU-50): "como no
 * sabemos en qué trabajo se cargan los gastos, ya el equipo está asociado a
 * equipos de inventario... así sabemos qué vehículo solicitó nuevo
 * combustible". FK real + entero plano a `per_equipos_trabajo` (ADR 0003
 * regla 3, mismo criterio que `base_id`/`trabajo_id`): sin `belongsTo`
 * cross-módulo, `Personal` se lee solo por contrato
 * (`Personal\Contratos\LecturaEquipoTrabajo`).
 *
 * Nullable: el gasto general (sin trabajo, sin base, sin equipo) sigue
 * existiendo — esta columna pasa a ser el camino PRINCIPAL de imputación
 * (el formulario la ofrece primero), pero no el único. Sin migración de
 * datos: los gastos que ya existen no tienen forma de inferir a qué equipo
 * pertenecían, mismo criterio que `campania_id` (tarea 69).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fin_gastos', function (Blueprint $table) {
            $table->unsignedBigInteger('equipo_trabajo_id')->nullable()->after('campania_id');
            $table->foreign('equipo_trabajo_id')->references('id')->on('per_equipos_trabajo')->restrictOnDelete();
            $table->index('equipo_trabajo_id');
        });
    }

    public function down(): void
    {
        Schema::table('fin_gastos', function (Blueprint $table) {
            $table->dropForeign(['equipo_trabajo_id']);
            $table->dropColumn('equipo_trabajo_id');
        });
    }
};
