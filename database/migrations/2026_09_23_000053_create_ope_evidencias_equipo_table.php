<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `ope_evidencias_equipo` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo OPE.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000053_create_ope_evidencias_equipo_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ope_evidencias_equipo')) {
            return;
        }

        Schema::create('ope_evidencias_equipo', function (Blueprint $table) {
            $table->id();
            $table->string('uuid_cliente', 36);
            $table->foreignId('trabajo_id')->constrained('ope_trabajos')->restrictOnDelete();
            $table->decimal('horas_vuelo_dron', 6, 2);
            $table->foreignId('foto_control_id')->constrained('ope_evidencias')->restrictOnDelete();
            $table->foreignId('foto_ciclo_bateria_balanceo_id')->constrained('ope_evidencias')->restrictOnDelete();
            $table->foreignId('foto_dron_limpio_id')->constrained('ope_evidencias')->restrictOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['trabajo_id'], 'ope_evidencias_equipo_trabajo_id_unique');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_evidencias_equipo_foto_ciclo_bateria_unico ON {$prefijo}ope_evidencias_equipo USING btree (foto_ciclo_bateria_balanceo_id) WHERE deleted_at IS NULL
        SQL);

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_evidencias_equipo_foto_control_unico ON {$prefijo}ope_evidencias_equipo USING btree (foto_control_id) WHERE deleted_at IS NULL
        SQL);

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_evidencias_equipo_foto_dron_limpio_unico ON {$prefijo}ope_evidencias_equipo USING btree (foto_dron_limpio_id) WHERE deleted_at IS NULL
        SQL);

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_evidencias_equipo_uuid_cliente_unico ON {$prefijo}ope_evidencias_equipo USING btree (uuid_cliente) WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_evidencias_equipo
                ADD CONSTRAINT {$prefijo}ope_evidencias_equipo_horas_vuelo_chk CHECK (horas_vuelo_dron >= 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ope_evidencias_equipo');
    }
};
