<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Operaciones — `modelo`/`capacidad_l` de `ope_drones` (HU-27, tarea
 * 36; espec §178, "administrar la flota de drones con su modelo y volumen de
 * carga"). `ope_drones` nació deliberadamente mínima (tarea 23, ver docblock
 * de `2026_09_01_100013_create_ope_drones_table.php`) hasta que esta HU
 * necesitara el dato — mismo patrón que `tarifa_ha` sobre `per_personas`
 * (tarea 16, `2026_09_01_200001_add_tarifa_ha_a_per_personas_table.php`).
 *
 * Ambas columnas nullable, sin default de negocio: los drones ya sembrados no
 * tienen modelo ni capacidad, y no hay un valor "razonable" que inventarles.
 * `modelo` es texto libre (ej. "DJI Agras T30") — sin catálogo cerrado, la
 * espec no lo pide. `capacidad_l` sí queda acotada al CHECK 30/50/60 (espec
 * §178: "volumen real por modelo (30/50/60 L)").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ope_drones', function (Blueprint $table) {
            $table->string('modelo', 40)->nullable()->after('identificador');
            $table->decimal('capacidad_l', 5, 2)->nullable()->after('modelo');
        });

        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_drones
                ADD CONSTRAINT {$prefijo}ope_drones_capacidad_l_chk
                    CHECK (capacidad_l IS NULL OR capacidad_l IN (30, 50, 60))
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_drones
                DROP CONSTRAINT {$prefijo}ope_drones_capacidad_l_chk
            SQL);
        }

        Schema::table('ope_drones', function (Blueprint $table) {
            $table->dropColumn(['modelo', 'capacidad_l']);
        });
    }
};
