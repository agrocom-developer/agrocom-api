<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Personal — `per_personas.tarifa_ha` se retira (pedido del dueño,
 * 22/9/2026; ADR 0023): registrar en la ficha de la persona lo que cobra por
 * un trabajo era un error de modelo — el pago es del trabajo. El valor por
 * defecto vive ahora en `fin_tarifas` y la condición de cada trabajo en
 * `ope_orden_trabajo_equipos`. Los devengos ya generados conservan su copia
 * congelada (`fin_devengos_personal.tarifa`), así que no se pierde ningún
 * monto pagado.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}per_personas
                DROP CONSTRAINT IF EXISTS {$prefijo}per_personas_tarifa_ha_chk
            SQL);
        }

        Schema::table('per_personas', function (Blueprint $table) {
            $table->dropColumn('tarifa_ha');
        });
    }

    public function down(): void
    {
        Schema::table('per_personas', function (Blueprint $table) {
            $table->decimal('tarifa_ha', 12, 2)->nullable()->after('rol');
        });
    }
};
