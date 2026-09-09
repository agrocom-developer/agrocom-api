<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Operaciones — cierre de sesión (espec §4.3, HU-05, tarea 13).
 * `motivo_cierre` es el catálogo de la espec: completado / relevo_piloto /
 * cambio_dron / falla_equipo / clima / fin_jornada / otro — guardado como
 * columna simple con su `CHECK`, sin la lógica de negocio de relevo
 * (hectárea acumulada de partida, tolerancia, trabajo `parcial`): eso es
 * HU-07 y necesita `dron_id`, que todavía no existe.
 *
 * `cierre_uuid_cliente`: mismo mecanismo y misma razón que
 * `ope_trabajos.cierre_uuid_cliente` (ver esa migración y runs/13.md) — el
 * `uuid_cliente` del EVENTO de cierre, no el de apertura de la sesión.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ope_sesiones', function (Blueprint $table) {
            $table->string('motivo_cierre', 20)->nullable()->after('fin');
            $table->string('cierre_uuid_cliente', 36)->nullable()->after('motivo_cierre');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_sesiones_cierre_uuid_cliente_unico
            ON {$prefijo}ope_sesiones (cierre_uuid_cliente)
            WHERE cierre_uuid_cliente IS NOT NULL AND deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_sesiones
                ADD CONSTRAINT {$prefijo}ope_sesiones_motivo_cierre_chk
                    CHECK (motivo_cierre IN (
                        'completado', 'relevo_piloto', 'cambio_dron',
                        'falla_equipo', 'clima', 'fin_jornada', 'otro'
                    ))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::table('ope_sesiones', function (Blueprint $table) {
            $table->dropColumn(['motivo_cierre', 'cierre_uuid_cliente']);
        });
    }
};
