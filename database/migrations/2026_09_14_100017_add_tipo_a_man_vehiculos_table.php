<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HU-90 (tarea 105): clasifica cada vehículo por tipo, catálogo cerrado
 * (`docs/negocio/observaciones_mantenimiento_2026-09-13.md`, fila HU-90) —
 * el pedido del dueño solo confirma que hay que sumar `chata` al catálogo
 * de `man_vehiculos`; no da el resto de los valores. Los identificadores
 * demo ya sembrados usan los prefijos `CAM-`/`MOT-`
 * (`database/seeders/Demo/FlotaDemoSeeder.php`), que fijan
 * `camioneta`/`camion`/`moto` como valores del dominio ya en uso
 * informalmente; el catálogo cerrado queda
 * `camioneta`/`camion`/`moto`/`chata`.
 *
 * Nullable sin valor por defecto: un vehículo existente (sembrado antes de
 * esta HU) no tiene tipo asignado todavía, y no hay un valor razonable que
 * inventarle — mismo criterio que `marca`/`modelo`/`combustible` en
 * `2026_09_14_100013_add_ficha_completa_y_pausa_a_man_vehiculos_table.php`.
 *
 * No confundir con `equipo_tipo` de `man_ordenes_mantenimiento`: ese campo
 * distingue en qué tabla vive el equipo (`dron`/`vehiculo`), no el tipo de
 * vehículo en sí — una chata sigue siendo `equipo_tipo = 'vehiculo'`
 * (confirmado con el usuario, mismo documento citado arriba). Esta columna
 * no lo toca.
 *
 * Solo pgsql para el `CHECK`: SQLite (tests locales) no soporta `ADD
 * CONSTRAINT`, mismo criterio que el resto de `man_vehiculos`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('man_vehiculos', function (Blueprint $table) {
            $table->string('tipo', 20)->nullable()->after('identificador');
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            ALTER TABLE {$prefijo}man_vehiculos
            ADD CONSTRAINT {$prefijo}man_vehiculos_tipo_chk
                CHECK (tipo IS NULL OR tipo IN ('camioneta', 'camion', 'moto', 'chata'))
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}man_vehiculos
                DROP CONSTRAINT {$prefijo}man_vehiculos_tipo_chk
            SQL);
        }

        Schema::table('man_vehiculos', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
};
