<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Comercial — decisión del dueño (16/9/2026): el rango horario
 * permitido para fumigar deja de ser un dato del contrato completo
 * (`com_contrato_ventanas`, N ventanas por contrato) y pasa a ser un dato de
 * CADA LOTE dentro del contrato — reemplazo completo, no coexistencia (ver
 * `2026_09_16_100004_elimina_com_contrato_ventanas_table.php`, que da de
 * baja la tabla vieja en la misma tanda).
 *
 * `hora_inicio`/`hora_fin` nullable: mismo criterio de "cero ventanas = día
 * completo" que ya tenía `com_contrato_ventanas` (HU-47), trasladado a nivel
 * de lote — acá un `ContratoLote` con AMBAS columnas en NULL significa "ese
 * lote, día completo". No son NULL independiente uno del otro: la app
 * valida que se completen las dos juntas o ninguna (mismo patrón que ya
 * usaban `CrearContratoRequest`/`ActualizarContratoRequest` con
 * `required_with` para las ventanas del contrato).
 *
 * Sin `UNIQUE` nueva: ya existe `com_contrato_lotes_contrato_lote_unico`
 * (contrato_id, lote_id) y ahora cada lote tiene A LO SUMO un rango horario
 * (una columna, no una lista) — no hace falta un
 * `ValidadorSolapamientoVentanas` equivalente por lote, porque no hay nada
 * que solape dentro de la misma fila. Esa era la razón de ser del
 * validador de solapes a nivel contrato (N filas por contrato); a nivel
 * lote no aplica.
 *
 * CHECK condicional (solo pgsql, mismo criterio que el resto del esquema
 * para checks que SQLite/tests no soportan vía `ALTER TABLE ADD
 * CONSTRAINT`): permite el caso "ambas NULL" (día completo) y exige
 * `hora_fin > hora_inicio` únicamente cuando las dos están cargadas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('com_contrato_lotes', function (Blueprint $table) {
            $table->time('hora_inicio')->nullable()->after('lote_id');
            $table->time('hora_fin')->nullable()->after('hora_inicio');
        });

        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}com_contrato_lotes
                ADD CONSTRAINT {$prefijo}com_contrato_lotes_horario_chk
                CHECK (
                    (hora_inicio IS NULL AND hora_fin IS NULL)
                    OR (hora_fin > hora_inicio)
                )
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}com_contrato_lotes
                DROP CONSTRAINT {$prefijo}com_contrato_lotes_horario_chk
            SQL);
        }

        Schema::table('com_contrato_lotes', function (Blueprint $table) {
            $table->dropColumn(['hora_inicio', 'hora_fin']);
        });
    }
};
