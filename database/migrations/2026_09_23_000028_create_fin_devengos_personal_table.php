<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `fin_devengos_personal` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo FIN.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000028_create_fin_devengos_personal_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fin_devengos_personal')) {
            return;
        }

        Schema::create('fin_devengos_personal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sesion_id')->constrained('ope_sesiones')->restrictOnDelete();
            $table->foreignId('persona_id')->constrained('per_personas')->restrictOnDelete();
            $table->decimal('hectareas', 10, 2);
            $table->decimal('tarifa', 12, 2);
            $table->decimal('monto', 12, 2);
            $table->date('fecha');
            $table->string('modalidad', 10);
            $table->foreignId('trabajo_id')->nullable()->constrained('ope_trabajos')->restrictOnDelete();
            $table->foreignId('absorbido_por_id')->nullable()->constrained('fin_devengos_personal')->restrictOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['persona_id'], 'fin_devengos_personal_persona_id_index');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}fin_devengos_personal_jornal_unico ON {$prefijo}fin_devengos_personal USING btree (persona_id, fecha) WHERE (modalidad = 'por_dia') AND (deleted_at IS NULL)
        SQL);

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}fin_devengos_personal_sesion_persona_unico ON {$prefijo}fin_devengos_personal USING btree (sesion_id, persona_id) WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}fin_devengos_personal
                ADD CONSTRAINT {$prefijo}fin_devengos_personal_hectareas_chk CHECK (hectareas >= 0),
                ADD CONSTRAINT {$prefijo}fin_devengos_personal_modalidad_chk CHECK (modalidad IN ('por_dia', 'por_ha')),
                ADD CONSTRAINT {$prefijo}fin_devengos_personal_monto_chk CHECK (monto >= 0),
                ADD CONSTRAINT {$prefijo}fin_devengos_personal_tarifa_chk CHECK (tarifa >= 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_devengos_personal');
    }
};
