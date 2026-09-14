<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HU-86 (tarea 101): separa "con cuántas horas entró el generador a la
 * flota" de "cuántas lleva hoy" — mismo espíritu que `ciclos_inicial`/
 * `ciclos_acumulados` de batería (tarea 98) y `kilometraje_inicial`/
 * `kilometraje_actual` de vehículo (tarea 99). A diferencia de esas dos,
 * acá hay dato existente que preservar: `horas_uso` ya se carga a mano
 * desde el alta original de la tabla (tarea 72), así que el `up()`
 * backfillea `horas_inicial = horas_actual = horas_uso` antes de dropear
 * la columna vieja — primera migración del repo que mezcla backfill de
 * datos con cambio de esquema en el mismo paso.
 *
 * `horas_actual >= horas_inicial` es CHECK, además de regla de
 * `ActualizarGeneradorRequest`/`CrearGeneradorRequest` — mismo criterio
 * "cinturón y tirantes" que el resto de columnas numéricas de `man_*`.
 *
 * Dropear `horas_uso` en Postgres se lleva consigo
 * `man_generadores_horas_uso_chk` (el CHECK depende solo de esa columna) —
 * no hace falta un `DROP CONSTRAINT` explícito, comprobado por
 * `tests/Feature/Mantenimiento/MigracionHorasGeneradorTest.php`.
 *
 * Solo pgsql para los `CHECK`: SQLite (tests locales) no soporta `ADD
 * CONSTRAINT`, mismo criterio que
 * `2026_09_14_100013_add_ficha_completa_y_pausa_a_man_vehiculos_table.php`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('man_generadores', function (Blueprint $table) {
            $table->decimal('horas_inicial', 8, 2)->nullable()->after('estado');
            $table->decimal('horas_actual', 8, 2)->nullable()->after('horas_inicial');
        });

        DB::table('man_generadores')
            ->whereNotNull('horas_uso')
            ->update([
                'horas_inicial' => DB::raw('horas_uso'),
                'horas_actual' => DB::raw('horas_uso'),
            ]);

        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}man_generadores
                ADD CONSTRAINT {$prefijo}man_generadores_horas_inicial_chk
                    CHECK (horas_inicial IS NULL OR horas_inicial >= 0)
            SQL);

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}man_generadores
                ADD CONSTRAINT {$prefijo}man_generadores_horas_actual_chk
                    CHECK (horas_actual IS NULL OR horas_actual >= 0)
            SQL);

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}man_generadores
                ADD CONSTRAINT {$prefijo}man_generadores_horas_actual_ge_inicial_chk
                    CHECK (horas_actual IS NULL OR horas_inicial IS NULL OR horas_actual >= horas_inicial)
            SQL);
        }

        Schema::table('man_generadores', function (Blueprint $table) {
            $table->dropColumn('horas_uso');
        });
    }

    public function down(): void
    {
        Schema::table('man_generadores', function (Blueprint $table) {
            $table->decimal('horas_uso', 8, 2)->nullable()->after('estado');
        });

        DB::table('man_generadores')
            ->whereNotNull('horas_actual')
            ->update(['horas_uso' => DB::raw('horas_actual')]);

        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}man_generadores
                DROP CONSTRAINT {$prefijo}man_generadores_horas_actual_ge_inicial_chk
            SQL);

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}man_generadores
                DROP CONSTRAINT {$prefijo}man_generadores_horas_actual_chk
            SQL);

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}man_generadores
                DROP CONSTRAINT {$prefijo}man_generadores_horas_inicial_chk
            SQL);

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}man_generadores
                ADD CONSTRAINT {$prefijo}man_generadores_horas_uso_chk
                    CHECK (horas_uso IS NULL OR horas_uso >= 0)
            SQL);
        }

        Schema::table('man_generadores', function (Blueprint $table) {
            $table->dropColumn(['horas_inicial', 'horas_actual']);
        });
    }
};
