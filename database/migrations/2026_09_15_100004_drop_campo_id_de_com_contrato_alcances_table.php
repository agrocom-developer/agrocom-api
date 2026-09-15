<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Comercial — `com_contrato_alcances` pierde `campo_id` (ADR 0020,
 * revierte ADR 0018): sin `Campo`, el alcance de un contrato es
 * `propiedad_id` (toda la propiedad) sin nivel intermedio que acotar más.
 *
 * El único parcial viejo usaba `COALESCE(campo_id, 0)` para no chocar dos
 * filas "toda la propiedad" (`campo_id` NULL) del mismo contrato/propiedad
 * — ver docblock de `create_com_contrato_alcances_table`. Sin `campo_id` no
 * hay ambigüedad de NULL que resolver: el único nuevo es
 * `(contrato_id, propiedad_id)` liso. Verificado contra el compose real:
 * `com_contrato_alcances` tiene 0 filas hoy, no hay pérdida de datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefijo = DB::getTablePrefix();

        // (a) dropear el único parcial viejo (referencia campo_id vía COALESCE).
        DB::statement("DROP INDEX IF EXISTS {$prefijo}com_contrato_alcances_unico");

        // (b) dropear FK, índice y columna. El índice btree plano sobre
        // campo_id NO se dropea en cascada junto con la columna en SQLite
        // (a diferencia de pgsql) — hay que dropearlo a mano, mismo criterio
        // que `add_propiedad_id_a_com_lotes_table`.
        Schema::table('com_contrato_alcances', function (Blueprint $table) {
            $table->dropForeign(['campo_id']);
            $table->dropIndex(['campo_id']);
            $table->dropColumn('campo_id');
        });

        // (c) recrear el único, ya sin COALESCE.
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}com_contrato_alcances_unico
            ON {$prefijo}com_contrato_alcances (contrato_id, propiedad_id)
            WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        $prefijo = DB::getTablePrefix();

        DB::statement("DROP INDEX IF EXISTS {$prefijo}com_contrato_alcances_unico");

        // Rollback de esquema, no de datos: recrea `campo_id` nullable sin
        // repoblarla (0 filas hoy, nada que reconstruir).
        Schema::table('com_contrato_alcances', function (Blueprint $table) {
            $table->foreignId('campo_id')->nullable()->after('propiedad_id')
                ->constrained('com_campos')->restrictOnDelete();
            $table->index('campo_id');
        });

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}com_contrato_alcances_unico
            ON {$prefijo}com_contrato_alcances (contrato_id, propiedad_id, COALESCE(campo_id, 0))
            WHERE deleted_at IS NULL
        SQL);
    }
};
