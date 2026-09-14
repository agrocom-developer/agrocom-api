<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HU-84 (tarea 99): completa `man_vehiculos` con los mismos datos de
 * inventario que ya tiene el resto de la flota
 * (`docs/negocio/observaciones_recursos_2026-09-13.md` §3) — marca, modelo,
 * año, combustible, si es 4x4 y kilometraje inicial/actual. A diferencia de
 * `ciclos_inicial` en baterías (tarea 98), `kilometraje_inicial` NO es
 * inmutable: el criterio de esta HU no lo pide, así que tanto
 * `CrearVehiculo` como `ActualizarVehiculo` lo reciben por igual.
 *
 * Suma también `pausa` al `CHECK` de `estado`: baja TEMPORAL de servicio,
 * distinta de `taller` (en reparación) y de `de_baja` (definitiva). Mismo
 * criterio "sin guardas" que el resto de `EstadoVehiculo` — agregar un caso
 * no gobierna ninguna transición (CLAUDE.md invariante 7 no aplica acá).
 *
 * Solo pgsql para los `CHECK`: SQLite (tests locales) no soporta `ADD
 * CONSTRAINT`, mismo criterio que
 * `2026_09_14_100012_add_ciclos_inicial_y_mantenimiento_a_man_baterias_table.php`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('man_vehiculos', function (Blueprint $table) {
            $table->string('marca', 60)->nullable()->after('identificador');
            $table->string('modelo', 60)->nullable()->after('marca');
            $table->unsignedSmallInteger('anio')->nullable()->after('modelo');
            $table->string('combustible', 20)->nullable()->after('anio');
            $table->boolean('es_4x4')->default(false)->after('combustible');
            $table->decimal('kilometraje_inicial', 10, 2)->nullable()->after('es_4x4');
            $table->decimal('kilometraje_actual', 10, 2)->nullable()->after('kilometraje_inicial');
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            ALTER TABLE {$prefijo}man_vehiculos
            ADD CONSTRAINT {$prefijo}man_vehiculos_combustible_chk
                CHECK (combustible IS NULL OR combustible IN ('gasolina', 'diesel'))
        SQL);

        DB::statement(<<<SQL
            ALTER TABLE {$prefijo}man_vehiculos
            ADD CONSTRAINT {$prefijo}man_vehiculos_kilometraje_inicial_chk
                CHECK (kilometraje_inicial >= 0)
        SQL);

        DB::statement(<<<SQL
            ALTER TABLE {$prefijo}man_vehiculos
            ADD CONSTRAINT {$prefijo}man_vehiculos_kilometraje_actual_chk
                CHECK (kilometraje_actual >= 0)
        SQL);

        DB::statement(<<<SQL
            ALTER TABLE {$prefijo}man_vehiculos
            DROP CONSTRAINT {$prefijo}man_vehiculos_estado_chk
        SQL);

        DB::statement(<<<SQL
            ALTER TABLE {$prefijo}man_vehiculos
            ADD CONSTRAINT {$prefijo}man_vehiculos_estado_chk
                CHECK (estado IN ('activo', 'taller', 'de_baja', 'pausa'))
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}man_vehiculos
                DROP CONSTRAINT {$prefijo}man_vehiculos_estado_chk
            SQL);

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}man_vehiculos
                ADD CONSTRAINT {$prefijo}man_vehiculos_estado_chk
                    CHECK (estado IN ('activo', 'taller', 'de_baja'))
            SQL);

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}man_vehiculos
                DROP CONSTRAINT {$prefijo}man_vehiculos_kilometraje_actual_chk
            SQL);

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}man_vehiculos
                DROP CONSTRAINT {$prefijo}man_vehiculos_kilometraje_inicial_chk
            SQL);

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}man_vehiculos
                DROP CONSTRAINT {$prefijo}man_vehiculos_combustible_chk
            SQL);
        }

        Schema::table('man_vehiculos', function (Blueprint $table) {
            $table->dropColumn([
                'marca',
                'modelo',
                'anio',
                'combustible',
                'es_4x4',
                'kilometraje_inicial',
                'kilometraje_actual',
            ]);
        });
    }
};
