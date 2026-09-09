<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `fin_combustibles` pasa a imputar al equipo de trabajo y al recurso
 * concreto que consumió la carga (tarea 73, HU-50): el problema real que
 * plantea el dueño — "no sabemos en qué trabajo se cargan los gastos... así
 * sabemos qué vehículo solicitó nuevo combustible" — es literalmente esta
 * tabla.
 *
 * - `equipo_trabajo_id`: FK real a `per_equipos_trabajo` (ADR 0003 regla 3),
 *   OBLIGATORIA — el combustible siempre lo consume una cuadrilla, a
 *   diferencia de `fin_gastos.equipo_trabajo_id` (nullable, el gasto general
 *   sigue existiendo). Nace nullable en el `ALTER TABLE` para poder
 *   backfillear las filas existentes antes de pasar a `NOT NULL` (mismo
 *   patrón que `2026_09_08_100002_add_campania_id_a_com_contratos_table`).
 * - `campania_id`: FK real a `cpn_campanias` (ADR 0015 punto 6), NULLABLE —
 *   en qué campaña se CONSUMIÓ la carga, atribución de costo nunca de cobro
 *   (el cliente paga por hectárea aplicada, no combustible). La carga es la
 *   unidad y no se prorratea: se atribuye entera a la campaña vigente al
 *   momento de la carga. Vacío = consumo interno, de ninguna campaña.
 * - `recurso_tipo` + `recurso_id` reemplazan a `destino`: mismo patrón
 *   polimórfico SIN FK que `per_equipo_recursos` (ver el docblock de
 *   `2026_09_08_500003_create_per_equipo_recursos_table`) — el destino de
 *   `recurso_id` cruza `ope_drones` (Operaciones) y `man_vehiculos`/
 *   `man_generadores` (Mantenimiento), y esta tabla es de `Finanzas`. La
 *   integridad la sostiene `Aplicacion/CrearCombustible`, que además exige
 *   que el recurso haya estado asignado al equipo elegido en la fecha de la
 *   carga (vía `Personal\Contratos\LecturaEquipoTrabajo::recursosAFecha()`),
 *   no solo que exista.
 *
 * Migración de datos, en la misma migración (mismo criterio que
 * `add_campania_id_a_com_contratos_table`): cada fila preexistente conserva
 * su `destino` como `recurso_tipo` tal cual, y se le asigna, por BASE (mejor
 * aproximación disponible sin dato real de cuál fue):
 * - `equipo_trabajo_id`: el primer equipo de trabajo de la misma base: si
 *   esa base no tiene ninguno, el primero que exista en todo el sistema.
 * - `recurso_id`: el primer recurso de esa base en la tabla que corresponde
 *   a `recurso_tipo` (`man_generadores`/`man_vehiculos`); si esa base no
 *   tiene ninguno, el primero que exista en toda la tabla.
 * Recién después de backfillear, `equipo_trabajo_id`/`recurso_tipo`/
 * `recurso_id` pasan a `NOT NULL` y se borra `destino`.
 *
 * `SET NOT NULL`/`DROP`/`ADD CONSTRAINT` solo en pgsql (SQLite, motor de los
 * tests, no soporta alterar columnas existentes sin `doctrine/dbal`, que
 * este repo no trae) — los tests verifican la invariante a nivel de
 * aplicación, mismo criterio que el resto de las migraciones de este repo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fin_combustibles', function (Blueprint $table) {
            $table->unsignedBigInteger('equipo_trabajo_id')->nullable()->after('base_id');
            $table->unsignedBigInteger('campania_id')->nullable()->after('equipo_trabajo_id');
            $table->string('recurso_tipo', 20)->nullable()->after('destino');
            $table->unsignedBigInteger('recurso_id')->nullable()->after('recurso_tipo');
        });

        $this->migrarFilasExistentes();

        Schema::table('fin_combustibles', function (Blueprint $table) {
            $table->foreign('equipo_trabajo_id')->references('id')->on('per_equipos_trabajo')->restrictOnDelete();
            $table->foreign('campania_id')->references('id')->on('cpn_campanias')->restrictOnDelete();
            $table->index('equipo_trabajo_id');
            $table->index('campania_id');
            $table->index(['recurso_tipo', 'recurso_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement("ALTER TABLE {$prefijo}fin_combustibles ALTER COLUMN equipo_trabajo_id SET NOT NULL");
            DB::statement("ALTER TABLE {$prefijo}fin_combustibles ALTER COLUMN recurso_tipo SET NOT NULL");
            DB::statement("ALTER TABLE {$prefijo}fin_combustibles ALTER COLUMN recurso_id SET NOT NULL");

            DB::statement("ALTER TABLE {$prefijo}fin_combustibles DROP CONSTRAINT {$prefijo}fin_combustibles_destino_chk");

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}fin_combustibles
                ADD CONSTRAINT {$prefijo}fin_combustibles_recurso_tipo_chk
                    CHECK (recurso_tipo IN ('dron', 'vehiculo', 'generador'))
            SQL);
        }

        Schema::table('fin_combustibles', function (Blueprint $table) {
            $table->dropColumn('destino');
        });
    }

    private function migrarFilasExistentes(): void
    {
        $tablaPorTipo = [
            'generador' => 'man_generadores',
            'vehiculo' => 'man_vehiculos',
        ];

        DB::table('fin_combustibles')->orderBy('id')->get(['id', 'base_id', 'destino'])->each(function (object $fila) use ($tablaPorTipo) {
            $equipoId = DB::table('per_equipos_trabajo')->where('base_id', $fila->base_id)->orderBy('id')->value('id')
                ?? DB::table('per_equipos_trabajo')->orderBy('id')->value('id');

            $tablaRecurso = $tablaPorTipo[$fila->destino];

            $recursoId = DB::table($tablaRecurso)->where('base_id', $fila->base_id)->orderBy('id')->value('id')
                ?? DB::table($tablaRecurso)->orderBy('id')->value('id');

            DB::table('fin_combustibles')->where('id', $fila->id)->update([
                'equipo_trabajo_id' => $equipoId,
                'recurso_tipo' => $fila->destino,
                'recurso_id' => $recursoId,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('fin_combustibles', function (Blueprint $table) {
            $table->string('destino', 20)->nullable()->after('base_id');
        });

        DB::table('fin_combustibles')->orderBy('id')->get(['id', 'recurso_tipo'])->each(function (object $fila) {
            DB::table('fin_combustibles')->where('id', $fila->id)->update(['destino' => $fila->recurso_tipo]);
        });

        Schema::table('fin_combustibles', function (Blueprint $table) {
            $table->dropForeign(['equipo_trabajo_id']);
            $table->dropForeign(['campania_id']);
            $table->dropIndex(['equipo_trabajo_id']);
            $table->dropIndex(['campania_id']);
            $table->dropIndex(['recurso_tipo', 'recurso_id']);
            $table->dropColumn(['equipo_trabajo_id', 'campania_id', 'recurso_tipo', 'recurso_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement("ALTER TABLE {$prefijo}fin_combustibles ALTER COLUMN destino SET NOT NULL");

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}fin_combustibles
                ADD CONSTRAINT {$prefijo}fin_combustibles_destino_chk
                    CHECK (destino IN ('generador', 'vehiculo'))
            SQL);
        }
    }
};
