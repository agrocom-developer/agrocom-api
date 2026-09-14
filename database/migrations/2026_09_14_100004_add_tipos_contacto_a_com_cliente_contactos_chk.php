<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * HU-75 (tarea 91): suma `gerente_general`, `finanzas` y `secretario` al
 * `CHECK` de `com_cliente_contactos.tipo` — contactos que hoy no tenían
 * dónde clasificarse. Solo agrega valores al enum de la base, sin quitar ni
 * reordenar los cuatro existentes (son datos ya persistidos) — mismo
 * criterio que `add_pausado_a_com_contratos_estado_chk`.
 *
 * Solo pgsql: SQLite no soporta `ADD CONSTRAINT`, igual que en
 * `create_com_cliente_contactos_table` — ver ese docblock.
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
            ALTER TABLE {$prefijo}com_cliente_contactos
            DROP CONSTRAINT {$prefijo}com_cliente_contactos_tipo_chk
        SQL);

        DB::statement(<<<SQL
            ALTER TABLE {$prefijo}com_cliente_contactos
            ADD CONSTRAINT {$prefijo}com_cliente_contactos_tipo_chk
                CHECK (tipo IN ('dueno', 'agronomo', 'encargado_propiedad', 'otro', 'gerente_general', 'finanzas', 'secretario'))
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            ALTER TABLE {$prefijo}com_cliente_contactos
            DROP CONSTRAINT {$prefijo}com_cliente_contactos_tipo_chk
        SQL);

        DB::statement(<<<SQL
            ALTER TABLE {$prefijo}com_cliente_contactos
            ADD CONSTRAINT {$prefijo}com_cliente_contactos_tipo_chk
                CHECK (tipo IN ('dueno', 'agronomo', 'encargado_propiedad', 'otro'))
        SQL);
    }
};
