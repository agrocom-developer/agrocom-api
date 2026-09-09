<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Personal — `tarifa_ha` de `per_personas` (espec §4.2; ADR 0011,
 * extensión 26/8/2026, punto 6: "se agregan por ALTER TABLE cuando se
 * implemente devengos/planilla" — HU-16, tarea 16, es esa implementación).
 *
 * Nullable, sin default de negocio: las `per_personas` creadas antes de esta
 * migración no tienen tarifa y no hay un valor "razonable" que inventarles —
 * decidir qué pasa cuando una persona sin `tarifa_ha` termina de piloto o
 * auxiliar de una sesión que se valida es responsabilidad del caso de uso de
 * `Finanzas` (`Aplicacion/GenerarDevengosSesion.php`), no de esta migración.
 * Documentado en runs/16.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('per_personas', function (Blueprint $table) {
            $table->decimal('tarifa_ha', 12, 2)->nullable()->after('rol');
        });

        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}per_personas
                ADD CONSTRAINT {$prefijo}per_personas_tarifa_ha_chk
                    CHECK (tarifa_ha IS NULL OR tarifa_ha >= 0)
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}per_personas
                DROP CONSTRAINT {$prefijo}per_personas_tarifa_ha_chk
            SQL);
        }

        Schema::table('per_personas', function (Blueprint $table) {
            $table->dropColumn('tarifa_ha');
        });
    }
};
