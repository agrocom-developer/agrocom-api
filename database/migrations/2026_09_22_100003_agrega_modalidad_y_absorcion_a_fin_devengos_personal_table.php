<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Finanzas — el devengo deja de ser solo «hectáreas × tarifa de la
 * persona» (ADR 0023, 22/9/2026):
 *
 * - `modalidad` (`por_ha` / `por_dia`): la condición con la que se pagó esa
 *   sesión. Las filas que ya existían eran todas por hectárea.
 * - `tarifa_ha` pasa a llamarse `tarifa`: el monto unitario, por hectárea o
 *   por día según la modalidad. Sigue siendo copia congelada al validar.
 * - `trabajo_id`: de qué trabajo salió la condición (referencia; nullable
 *   porque el devengo puede haberse calculado con la tarifa predeterminada
 *   cuando el trabajo no tiene condición propia).
 * - `absorbido_por_id`: decisión del dueño (22/9/2026) para el día mixto —si
 *   una persona el mismo día cobra un jornal, ese jornal absorbe lo que haya
 *   por hectárea ese día. La fila por hectárea NO se borra ni se toca su
 *   monto (queda como registro de lo trabajado, invariante 6: se puede
 *   recalcular); solo apunta al jornal que la absorbe, y las sumas la
 *   excluyen. Los lectores usan `DevengoPersonal::pagables()`.
 *
 * El jornal es idempotente por persona y fecha: índice único parcial sobre
 * (`persona_id`, `fecha`) para `modalidad = 'por_dia'`, además del
 * `(sesion_id, persona_id)` que ya existía. Dos sesiones del mismo día por
 * jornal generan UN devengo (la segunda choca acá y el caso de uso lo trata
 * como ya aplicado).
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefijo = DB::getTablePrefix();

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}fin_devengos_personal
                DROP CONSTRAINT IF EXISTS {$prefijo}fin_devengos_personal_tarifa_ha_chk
            SQL);
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

        // Las filas previas nacieron todas por hectárea. Sin default de
        // columna: de acá en más la modalidad la pone siempre el caso de uso.
        DB::table('fin_devengos_personal')->whereNull('modalidad')->update(['modalidad' => 'por_ha']);

        DB::statement(<<<SQL
            ALTER TABLE {$prefijo}fin_devengos_personal
            ALTER COLUMN modalidad SET NOT NULL
        SQL);

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}fin_devengos_personal_jornal_unico
            ON {$prefijo}fin_devengos_personal (persona_id, fecha)
            WHERE modalidad = 'por_dia' AND deleted_at IS NULL
        SQL);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}fin_devengos_personal
                ADD CONSTRAINT {$prefijo}fin_devengos_personal_modalidad_chk
                    CHECK (modalidad IN ('por_dia', 'por_ha')),
                ADD CONSTRAINT {$prefijo}fin_devengos_personal_tarifa_chk
                    CHECK (tarifa >= 0)
            SQL);
        }
    }

    public function down(): void
    {
        $prefijo = DB::getTablePrefix();

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}fin_devengos_personal
                DROP CONSTRAINT IF EXISTS {$prefijo}fin_devengos_personal_modalidad_chk,
                DROP CONSTRAINT IF EXISTS {$prefijo}fin_devengos_personal_tarifa_chk
            SQL);
        }

        DB::statement("DROP INDEX IF EXISTS {$prefijo}fin_devengos_personal_jornal_unico");

        Schema::table('fin_devengos_personal', function (Blueprint $table) {
            $table->dropConstrainedForeignId('absorbido_por_id');
            $table->dropConstrainedForeignId('trabajo_id');
            $table->dropColumn('modalidad');
            $table->renameColumn('tarifa', 'tarifa_ha');
        });
    }
};
