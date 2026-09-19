<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ADR 0022, adenda del 19/9/2026: la causa de cancelación de una orden pasa a
 * tener tres valores — `cliente`, `dueno` (decisión de Agrocom) y
 * `factor_externo` (lo que hasta hoy se llamaba `fuerza_mayor`: un dron caído,
 * el clima) — y solo `factor_externo` deja de consumir el número de aplicación.
 *
 * Renombrar el valor toca DOS garantías de la base, por eso va en una migración
 * y no solo en la aplicación:
 * - el `CHECK` de `causa_cancelacion` (solo pgsql: SQLite no soporta
 *   `ADD CONSTRAINT`), y
 * - el índice único parcial `nro_por_contrato`, que deja repetir el número entre
 *   canceladas por la causa que no lo consume. Se suelta ANTES de renombrar los
 *   datos (con el valor nuevo esas filas entrarían al índice y chocarían con la
 *   orden que las rehace) y se vuelve a crear con el valor nuevo.
 *
 * Los datos existentes con `fuerza_mayor` pasan a `factor_externo`; `dueno` no
 * tiene equivalente previo. En `down()`, `dueno` vuelve a `cliente` (la causa
 * más cercana que sí consume el número).
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefijo = DB::getTablePrefix();

        DB::statement("DROP INDEX IF EXISTS {$prefijo}ope_ordenes_aplicacion_nro_por_contrato");

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE {$prefijo}ope_ordenes_aplicacion DROP CONSTRAINT {$prefijo}ope_ordenes_aplicacion_causa_cancelacion_chk");
        }

        DB::table('ope_ordenes_aplicacion')
            ->where('causa_cancelacion', 'fuerza_mayor')
            ->update(['causa_cancelacion' => 'factor_externo']);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_ordenes_aplicacion
                ADD CONSTRAINT {$prefijo}ope_ordenes_aplicacion_causa_cancelacion_chk
                    CHECK (causa_cancelacion IS NULL OR causa_cancelacion IN ('cliente', 'dueno', 'factor_externo'))
            SQL);
        }

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_ordenes_aplicacion_nro_por_contrato
            ON {$prefijo}ope_ordenes_aplicacion (contrato_id, nro_aplicacion)
            WHERE deleted_at IS NULL AND NOT (estado = 'cancelada' AND causa_cancelacion = 'factor_externo')
        SQL);
    }

    public function down(): void
    {
        $prefijo = DB::getTablePrefix();

        DB::statement("DROP INDEX IF EXISTS {$prefijo}ope_ordenes_aplicacion_nro_por_contrato");

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE {$prefijo}ope_ordenes_aplicacion DROP CONSTRAINT {$prefijo}ope_ordenes_aplicacion_causa_cancelacion_chk");
        }

        DB::table('ope_ordenes_aplicacion')->where('causa_cancelacion', 'dueno')->update(['causa_cancelacion' => 'cliente']);
        DB::table('ope_ordenes_aplicacion')->where('causa_cancelacion', 'factor_externo')->update(['causa_cancelacion' => 'fuerza_mayor']);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_ordenes_aplicacion
                ADD CONSTRAINT {$prefijo}ope_ordenes_aplicacion_causa_cancelacion_chk
                    CHECK (causa_cancelacion IS NULL OR causa_cancelacion IN ('cliente', 'fuerza_mayor'))
            SQL);
        }

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_ordenes_aplicacion_nro_por_contrato
            ON {$prefijo}ope_ordenes_aplicacion (contrato_id, nro_aplicacion)
            WHERE deleted_at IS NULL AND NOT (estado = 'cancelada' AND causa_cancelacion = 'fuerza_mayor')
        SQL);
    }
};
