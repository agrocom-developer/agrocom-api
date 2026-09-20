<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tarea "cuadrillas-estadias" (19/9/2026): suma `bateria` al `CHECK` de
 * `per_equipo_recursos.recurso_tipo` — una cuadrilla lleva, además de dron,
 * vehículo y generador, la cantidad de baterías que se le asignaron
 * (`RecursoTipoEquipo::Bateria`, destino `man_baterias`, mismo criterio
 * polimórfico sin FK que el resto de los tipos — ver el docblock de
 * `2026_09_08_500003_create_per_equipo_recursos_table.php`). Solo agrega un
 * valor al enum de la base: no reescribe la tabla ni migra filas existentes,
 * mismo criterio que `add_conflicto_a_com_contratos_estado_chk`.
 *
 * Solo pgsql: SQLite no soporta `ADD CONSTRAINT`, igual que en la migración
 * que crea la tabla.
 *
 * `down()` no necesita reasignar filas antes de restaurar el `CHECK` viejo
 * (a diferencia de `add_conflicto_a_com_contratos_estado_chk`): revertir esta
 * migración solo tiene sentido antes de que exista ninguna fila con
 * `recurso_tipo = 'bateria'`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            ALTER TABLE {$prefijo}per_equipo_recursos
            DROP CONSTRAINT {$prefijo}per_equipo_recursos_tipo_chk
        SQL);

        DB::statement(<<<SQL
            ALTER TABLE {$prefijo}per_equipo_recursos
            ADD CONSTRAINT {$prefijo}per_equipo_recursos_tipo_chk
                CHECK (recurso_tipo IN ('dron', 'vehiculo', 'generador', 'bateria'))
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            ALTER TABLE {$prefijo}per_equipo_recursos
            DROP CONSTRAINT {$prefijo}per_equipo_recursos_tipo_chk
        SQL);

        DB::statement(<<<SQL
            ALTER TABLE {$prefijo}per_equipo_recursos
            ADD CONSTRAINT {$prefijo}per_equipo_recursos_tipo_chk
                CHECK (recurso_tipo IN ('dron', 'vehiculo', 'generador'))
        SQL);
    }
};
