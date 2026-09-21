<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ADR 0021 (18/9/2026): suma `conflicto` al `CHECK` de `com_contratos.estado` —
 * un contrato `borrador` que comparte lotes con otro que ya los retiene en la
 * misma campaña (ver `EstadoContrato` y `TransicionesContrato`). Solo agrega
 * un valor al enum de la base: no reescribe la tabla ni migra filas
 * existentes, mismo criterio que `add_pausado_a_com_contratos_estado_chk`.
 *
 * Solo pgsql: SQLite no soporta `ADD CONSTRAINT`, igual que en
 * `create_com_contratos_table` — ver ese docblock.
 *
 * `down()` devuelve a `borrador` los contratos que estén en `conflicto` antes
 * de volver a poner el `CHECK` de cinco estados: sin eso, el rollback fallaría
 * contra esas filas.
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
            ALTER TABLE {$prefijo}com_contratos
            DROP CONSTRAINT {$prefijo}com_contratos_estado_chk
        SQL);

        DB::statement(<<<SQL
            ALTER TABLE {$prefijo}com_contratos
            ADD CONSTRAINT {$prefijo}com_contratos_estado_chk
                CHECK (estado IN ('borrador', 'vigente', 'finalizado', 'cancelado', 'pausado', 'conflicto'))
        SQL);
    }

    public function down(): void
    {
        DB::table('com_contratos')->where('estado', 'conflicto')->update(['estado' => 'borrador']);

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            ALTER TABLE {$prefijo}com_contratos
            DROP CONSTRAINT {$prefijo}com_contratos_estado_chk
        SQL);

        DB::statement(<<<SQL
            ALTER TABLE {$prefijo}com_contratos
            ADD CONSTRAINT {$prefijo}com_contratos_estado_chk
                CHECK (estado IN ('borrador', 'vigente', 'finalizado', 'cancelado', 'pausado'))
        SQL);
    }
};
