<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `ope_sesiones` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo OPE.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000027_create_ope_sesiones_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ope_sesiones')) {
            return;
        }

        Schema::create('ope_sesiones', function (Blueprint $table) {
            $table->id();
            $table->string('uuid_cliente', 36);
            $table->foreignId('trabajo_id')->constrained('ope_trabajos')->restrictOnDelete();
            $table->smallInteger('secuencia');
            $table->foreignId('piloto_id')->constrained('per_personas')->restrictOnDelete();
            $table->foreignId('auxiliar_id')->nullable()->constrained('per_personas')->restrictOnDelete();
            $table->decimal('hectareas_declaradas', 10, 2)->default(0);
            $table->string('estado', 20)->default('abierto');
            $table->dateTime('inicio');
            $table->dateTime('fin')->nullable();
            $table->string('motivo_cierre', 20)->nullable();
            $table->string('cierre_uuid_cliente', 36)->nullable();
            $table->foreignId('validado_por')->nullable()->constrained('per_personas')->restrictOnDelete();
            $table->dateTime('fecha_validacion')->nullable();
            $table->dateTime('anulada_en')->nullable();
            $table->decimal('litros_consumidos', 10, 2)->nullable();
            $table->foreignId('dron_id')->nullable()->constrained('ope_drones')->restrictOnDelete();
            $table->decimal('hectarea_inicial_acumulada', 10, 2)->nullable();
            $table->foreignId('captura_rc_id')->nullable()->constrained('ope_evidencias')->nullOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['piloto_id'], 'ope_sesiones_piloto_id_index');
            $table->index(['trabajo_id', 'secuencia'], 'ope_sesiones_trabajo_id_secuencia_index');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_sesiones_cierre_uuid_cliente_unico ON {$prefijo}ope_sesiones USING btree (cierre_uuid_cliente) WHERE (cierre_uuid_cliente IS NOT NULL) AND (deleted_at IS NULL)
        SQL);

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_sesiones_uuid_cliente_unico ON {$prefijo}ope_sesiones USING btree (uuid_cliente) WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_sesiones
                ADD CONSTRAINT {$prefijo}ope_sesiones_estado_chk CHECK (estado IN ('abierto', 'cerrado', 'validado')),
                ADD CONSTRAINT {$prefijo}ope_sesiones_hectarea_inicial_acumulada_chk CHECK ((hectarea_inicial_acumulada IS NULL) OR (hectarea_inicial_acumulada >= 0)),
                ADD CONSTRAINT {$prefijo}ope_sesiones_hectareas_declaradas_chk CHECK (hectareas_declaradas >= 0),
                ADD CONSTRAINT {$prefijo}ope_sesiones_litros_consumidos_chk CHECK ((litros_consumidos IS NULL) OR (litros_consumidos >= 0)),
                ADD CONSTRAINT {$prefijo}ope_sesiones_motivo_cierre_chk CHECK (motivo_cierre IN ('completado', 'relevo_piloto', 'cambio_dron', 'falla_equipo', 'clima', 'fin_jornada', 'otro')),
                ADD CONSTRAINT {$prefijo}ope_sesiones_secuencia_chk CHECK (secuencia >= 1)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ope_sesiones');
    }
};
