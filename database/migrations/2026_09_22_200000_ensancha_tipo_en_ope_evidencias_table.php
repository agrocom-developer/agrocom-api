<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `ope_evidencias.tipo` nació como `varchar(20)` con cinco valores cortos.
 * HU-80 (PR #190) sumó al CHECK `foto_control`, `foto_ciclo_bateria_balanceo`
 * y `foto_dron_limpio`, pero nadie ensanchó la columna: el segundo tiene 27
 * caracteres, así que Postgres rechazaba la fila con «value too long» antes
 * de llegar al CHECK, y el reporte de equipos nunca pudo guardar esa foto.
 * Lo destapó el seeder demo (22/9/2026).
 *
 * Cambio a una tabla ya publicada: migración aparte, nunca se toca el
 * `create` consolidado (ADR 0024).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $prefijo = DB::getTablePrefix();

        DB::statement("ALTER TABLE {$prefijo}ope_evidencias ALTER COLUMN tipo TYPE varchar(40)");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $prefijo = DB::getTablePrefix();

        DB::statement("ALTER TABLE {$prefijo}ope_evidencias ALTER COLUMN tipo TYPE varchar(20)");
    }
};
