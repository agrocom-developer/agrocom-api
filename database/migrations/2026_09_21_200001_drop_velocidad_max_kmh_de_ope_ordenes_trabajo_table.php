<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La Orden de Trabajo deja de pedir `velocidad_max_kmh` (decisión del dueño,
 * 21/9/2026): en «Parámetros de vuelo» convivía con `velocidad_vuelo_kmh` y
 * decían lo mismo. La máxima nació como tope del CONTRATO (RF-60, "velocidad
 * ≤15 km/h impuesta"); desde que el contrato dejó de llevar parámetros de
 * vuelo (HU-91) y la orden fija la velocidad a la que se vuela, el tope que
 * imponga un cliente se carga directamente como velocidad de vuelo.
 *
 * Sin migración de datos: ninguna orden de trabajo la tenía cargada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_ordenes_trabajo
                DROP CONSTRAINT {$prefijo}ope_ordenes_trabajo_velocidad_max_chk
            SQL);
        }

        Schema::table('ope_ordenes_trabajo', function (Blueprint $table) {
            $table->dropColumn('velocidad_max_kmh');
        });
    }

    public function down(): void
    {
        Schema::table('ope_ordenes_trabajo', function (Blueprint $table) {
            $table->decimal('velocidad_max_kmh', 5, 2)->nullable()->after('humedad_max_pct');
        });

        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_ordenes_trabajo
                ADD CONSTRAINT {$prefijo}ope_ordenes_trabajo_velocidad_max_chk
                    CHECK (velocidad_max_kmh IS NULL OR velocidad_max_kmh > 0)
            SQL);
        }
    }
};
