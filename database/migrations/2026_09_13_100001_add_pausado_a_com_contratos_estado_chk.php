<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * HU-71 (tarea 87): suma `pausado` al `CHECK` de `com_contratos.estado` —
 * interrupción del contrato vigente, no una cancelación (ver `EstadoContrato`
 * y `TransicionesContrato`). Solo agrega un valor al enum de la base: no
 * reescribe la tabla ni migra filas existentes, mismo criterio que el resto
 * de los `ALTER` de este repo (ADR 0001, checks en la base, no solo en la
 * aplicación).
 *
 * Solo pgsql: SQLite no soporta `ADD CONSTRAINT`, igual que en
 * `create_com_contratos_table` — ver ese docblock.
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
                CHECK (estado IN ('borrador', 'vigente', 'finalizado', 'cancelado', 'pausado'))
        SQL);
    }

    public function down(): void
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
                CHECK (estado IN ('borrador', 'vigente', 'finalizado', 'cancelado'))
        SQL);
    }
};
