<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `ALTER TABLE com_contratos ADD altura_vuelo_m` (HU-47, tarea 70): la altura
 * de vuelo ya se pacta con el cliente por contrato en la práctica (rol_piloto
 * §128 — desacuerdo real en campo, 4-5 m según Josué, 2-3 m según Miguelito),
 * pero hasta ahora solo existía como parámetro de la orden
 * (`ope_ordenes_aplicacion.altura_vuelo_m`, un nivel más abajo). NULL = no
 * pactada por contrato, rige lo que diga la orden — mismo criterio de
 * "límite en NULL hereda" que el resto de los parámetros por contrato
 * (`velocidad_max_kmh` y compañía).
 *
 * `CHECK` solo en pgsql (SQLite no soporta `ADD CONSTRAINT` vía Blueprint,
 * mismo motivo que el resto de las migraciones de este repo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('com_contratos', function (Blueprint $table) {
            $table->decimal('altura_vuelo_m', 5, 2)->nullable()->after('velocidad_max_kmh');
        });

        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}com_contratos
                ADD CONSTRAINT {$prefijo}com_contratos_altura_vuelo_chk
                CHECK (altura_vuelo_m IS NULL OR altura_vuelo_m > 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::table('com_contratos', function (Blueprint $table) {
            $table->dropColumn('altura_vuelo_m');
        });
    }
};
