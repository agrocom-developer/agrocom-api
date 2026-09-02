<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Operaciones — sobrante de caldo al cierre del trabajo (espec §7.2,
 * HU-10 redefinida por CR-01, tarea 18). NULLABLE y OPCIONAL en el DTO
 * (`CierreTrabajo::$litrosSobrante`), mismo motivo que
 * `ope_sesiones.litros_consumidos` (ver esa migración): no es condición de la
 * transición `trabajo → cerrado`, y exigirlo habría rechazado en el acto
 * todo el lote de tests de cierre de trabajo ya verdes (tarea 13) que no
 * declaran litros.
 *
 * A diferencia de `hectareas_declaradas` del propio `ope_trabajos` (que es
 * DERIVADO — recalculado como suma de sesiones, nunca un dato que el cliente
 * declare en el cierre, ver docblock de `CierreTrabajo`), `litros_sobrante`
 * SÍ es un dato declarado directamente: no hay forma de derivarlo de otras
 * filas (a diferencia de `recibido`/`consumido`, que sí se recalculan desde
 * `ope_recepciones_caldo` y `ope_sesiones.litros_consumidos` — ver
 * `Trabajo::cuadreCaldo()`), la propia espec (§7.2) lo describe como "cuánto
 * quedó sin aplicar al cerrar", una medición del piloto/auxiliar al vaciar el
 * tanque, no una suma de registros previos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ope_trabajos', function (Blueprint $table) {
            $table->decimal('litros_sobrante', 10, 2)->nullable()->after('hectareas_declaradas');
        });

        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_trabajos
                ADD CONSTRAINT {$prefijo}ope_trabajos_litros_sobrante_chk
                    CHECK (litros_sobrante IS NULL OR litros_sobrante >= 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::table('ope_trabajos', function (Blueprint $table) {
            $table->dropColumn('litros_sobrante');
        });
    }
};
