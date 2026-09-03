<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `ALTER TABLE fin_gastos ADD rendicion_id` (HU-34, tarea 48) — la columna
 * que `create_fin_gastos_table` (tarea 47) ya anticipaba en su docblock
 * ("la agrega la tarea 48 por ALTER TABLE cuando exista fin_rendiciones"),
 * mismo criterio que `capacidad_l` en `ope_drones` (tarea 36): la tabla nace
 * sin la columna porque `fin_rendiciones` todavía no existe, se agrega acá
 * una vez que sí.
 *
 * Nullable: un gasto puede seguir siendo "general" (imputado a base/trabajo o
 * a ninguno) sin pertenecer a ninguna rendición — asociarlo es un paso
 * posterior y explícito (`Aplicacion/AsociarGastoARendicion`), no algo que
 * toda alta de gasto deba resolver.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fin_gastos', function (Blueprint $table) {
            $table->unsignedBigInteger('rendicion_id')->nullable()->after('trabajo_id');
            $table->foreign('rendicion_id')->references('id')->on('fin_rendiciones')->restrictOnDelete();
            $table->index('rendicion_id');
        });
    }

    public function down(): void
    {
        Schema::table('fin_gastos', function (Blueprint $table) {
            $table->dropForeign(['rendicion_id']);
            $table->dropColumn('rendicion_id');
        });
    }
};
