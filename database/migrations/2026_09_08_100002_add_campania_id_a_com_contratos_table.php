<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `ALTER TABLE com_contratos ADD campania_id` (ADR 0015 punto 1, tarea 69,
 * corregido el 8/9/2026): el contrato es con un cliente y para una campaña
 * SUYA. FK real + entero plano (ADR 0003 regla 3, nunca `belongsTo`
 * cross-módulo) — mismo criterio que `sec_user.persona_id`.
 *
 * No se squashea contra `create_com_contratos_table` (ADR 0018 §4): esa
 * migración es del 26/8/2026, y `cpn_campanias` (módulo Campania, ADR 0015)
 * nace el 8/9/2026 — antes de esa fecha la FK no tendría a qué apuntar.
 *
 * Se le sacó el paso intermedio nullable + backfill que tuvo la primera
 * versión de esta migración (con `com_contratos` siempre vacía en este
 * punto — la tabla recién creada, sin seeds todavía — no hay filas
 * preexistentes que requieran una campaña retroactiva), pero se mantiene
 * `nullable()` a nivel de `Schema::table` + el `SET NOT NULL` solo en pgsql
 * de la versión original: es el mismo criterio que el resto de las
 * migraciones del repo (SQLite no soporta `ALTER COLUMN ... SET NOT NULL`
 * vía Blueprint) y cambiarlo acá — reforzando el NOT NULL también en
 * SQLite — es una decisión aparte de ADR 0015, no de este rediseño; los
 * tests de todo el repo asumen hoy que `campania_id` es opcional en la
 * base de tests SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('com_contratos', function (Blueprint $table) {
            $table->foreignId('campania_id')->nullable()->after('cliente_id')->constrained('cpn_campanias')->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement("ALTER TABLE {$prefijo}com_contratos ALTER COLUMN campania_id SET NOT NULL");
        }
    }

    public function down(): void
    {
        Schema::table('com_contratos', function (Blueprint $table) {
            $table->dropForeign(['campania_id']);
            $table->dropColumn('campania_id');
        });
    }
};
