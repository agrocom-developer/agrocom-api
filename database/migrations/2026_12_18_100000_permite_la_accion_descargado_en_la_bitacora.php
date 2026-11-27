<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La bitácora admite la acción `descargado`: constancia de quién abrió o descargó el original de un archivo
 * sensible (documentos de identidad y licencia). Solo ensancha el CHECK de `plt_bitacoras.accion`; no toca
 * filas. El CHECK solo existe en PostgreSQL (SQLite no soporta ADD CONSTRAINT).
 *
 * `down()` vuelve al CHECK de tres acciones como `NOT VALID`: no revisa las filas `descargado` que ya
 * existan (la constancia de auditoría no se borra para poder volver atrás).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $tabla = DB::getTablePrefix().'plt_bitacoras';

        DB::statement("ALTER TABLE {$tabla} DROP CONSTRAINT IF EXISTS {$tabla}_accion_chk");
        DB::statement("ALTER TABLE {$tabla} ADD CONSTRAINT {$tabla}_accion_chk CHECK (accion IN ('creado', 'actualizado', 'eliminado', 'descargado'))");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $tabla = DB::getTablePrefix().'plt_bitacoras';

        DB::statement("ALTER TABLE {$tabla} DROP CONSTRAINT IF EXISTS {$tabla}_accion_chk");
        DB::statement("ALTER TABLE {$tabla} ADD CONSTRAINT {$tabla}_accion_chk CHECK (accion IN ('creado', 'actualizado', 'eliminado')) NOT VALID");
    }
};
