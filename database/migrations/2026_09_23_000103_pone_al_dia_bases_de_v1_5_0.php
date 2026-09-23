<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Puente para las bases que quedaron a mitad de las migraciones legadas
 * (producción, en `v1.5.0`). La consolidación (ADR 0024) es posterior a ese
 * tag: cada `create` consolidada se saltea con `hasTable` porque supone que la
 * base ya corrió TODAS las legadas, y en producción faltan dos cambios sobre
 * tablas que sí existían (los de ADR 0023, «pago por trabajo»):
 *
 *   - `fin_devengos_personal`: `tarifa_ha` pasa a `tarifa` y aparecen
 *     `modalidad`, `trabajo_id` y `absorbido_por_id` (con su índice del
 *     jornal único y sus CHECK). Sin esto, el devengo al validar una sesión
 *     (invariante 3) no puede escribir.
 *   - `per_personas`: se retira `tarifa_ha` (el pago es del trabajo).
 *
 * Cada bloque se guarda con `hasColumn`: en una base recién creada o en la del
 * compose (que ya pasó por las legadas) no hace nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->actualizarDevengos();
        $this->retirarTarifaDePersonas();
    }

    public function down(): void
    {
        // Las bases con la forma vieja no se reconstruyen: no hay a qué volver.
    }

    private function actualizarDevengos(): void
    {
        if (! Schema::hasColumn('fin_devengos_personal', 'tarifa_ha')) {
            return;
        }

        $prefijo = DB::getTablePrefix();
        $pgsql = DB::getDriverName() === 'pgsql';

        if ($pgsql) {
            DB::statement("ALTER TABLE {$prefijo}fin_devengos_personal DROP CONSTRAINT IF EXISTS {$prefijo}fin_devengos_personal_tarifa_ha_chk");
        }

        Schema::table('fin_devengos_personal', function (Blueprint $table) {
            $table->renameColumn('tarifa_ha', 'tarifa');
        });

        Schema::table('fin_devengos_personal', function (Blueprint $table) {
            $table->string('modalidad', 10)->nullable()->after('persona_id');
            $table->foreignId('trabajo_id')->nullable()->after('sesion_id')
                ->constrained('ope_trabajos')->restrictOnDelete();
            $table->foreignId('absorbido_por_id')->nullable()->after('fecha')
                ->constrained('fin_devengos_personal')->restrictOnDelete();
        });

        // Las filas previas nacieron todas por hectárea.
        DB::table('fin_devengos_personal')->whereNull('modalidad')->update(['modalidad' => 'por_ha']);

        DB::statement("ALTER TABLE {$prefijo}fin_devengos_personal ALTER COLUMN modalidad SET NOT NULL");

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX IF NOT EXISTS {$prefijo}fin_devengos_personal_jornal_unico
            ON {$prefijo}fin_devengos_personal (persona_id, fecha)
            WHERE modalidad = 'por_dia' AND deleted_at IS NULL
        SQL);

        if ($pgsql) {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}fin_devengos_personal
                ADD CONSTRAINT {$prefijo}fin_devengos_personal_modalidad_chk CHECK (modalidad IN ('por_dia', 'por_ha')),
                ADD CONSTRAINT {$prefijo}fin_devengos_personal_tarifa_chk CHECK (tarifa >= 0)
            SQL);
        }
    }

    private function retirarTarifaDePersonas(): void
    {
        if (! Schema::hasColumn('per_personas', 'tarifa_ha')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement("ALTER TABLE {$prefijo}per_personas DROP CONSTRAINT IF EXISTS {$prefijo}per_personas_tarifa_ha_chk");
        }

        Schema::table('per_personas', function (Blueprint $table) {
            $table->dropColumn('tarifa_ha');
        });
    }
};
