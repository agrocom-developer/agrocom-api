<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Amplía el `CHECK` de `ope_evidencias.tipo` (creado en
 * `2026_09_01_100012_create_ope_evidencias_table`) con los tres tipos nuevos
 * de `TipoEvidencia` (HU-80, tarea 86: `foto_control`,
 * `foto_ciclo_bateria_balanceo`, `foto_dron_limpio`). Rangos y enums en la
 * base, no solo en la aplicación (ADR 0001) — sin esto, `POST /api/evidencias`
 * con cualquiera de estos tipos nuevos insertaría en SQLite (tests) pero
 * fallaría en Postgres (producción).
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
            ALTER TABLE {$prefijo}ope_evidencias
            DROP CONSTRAINT {$prefijo}ope_evidencias_tipo_chk
        SQL);

        DB::statement(<<<SQL
            ALTER TABLE {$prefijo}ope_evidencias
            ADD CONSTRAINT {$prefijo}ope_evidencias_tipo_chk
                CHECK (tipo IN (
                    'captura_rc', 'imagen_campo', 'foto_incidencia', 'comprobante', 'firma_acta',
                    'foto_control', 'foto_ciclo_bateria_balanceo', 'foto_dron_limpio'
                ))
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            ALTER TABLE {$prefijo}ope_evidencias
            DROP CONSTRAINT {$prefijo}ope_evidencias_tipo_chk
        SQL);

        DB::statement(<<<SQL
            ALTER TABLE {$prefijo}ope_evidencias
            ADD CONSTRAINT {$prefijo}ope_evidencias_tipo_chk
                CHECK (tipo IN ('captura_rc', 'imagen_campo', 'foto_incidencia', 'comprobante', 'firma_acta'))
        SQL);
    }
};
