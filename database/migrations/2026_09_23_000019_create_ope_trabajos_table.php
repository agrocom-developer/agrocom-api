<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `ope_trabajos` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo OPE.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000019_create_ope_trabajos_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ope_trabajos')) {
            return;
        }

        Schema::create('ope_trabajos', function (Blueprint $table) {
            $table->id();
            $table->string('uuid_cliente', 36);
            $table->foreignId('orden_id')->constrained('ope_ordenes_aplicacion')->restrictOnDelete();
            $table->foreignId('lote_id')->constrained('com_lotes')->restrictOnDelete();
            $table->smallInteger('nro_aplicacion');
            $table->decimal('hectareas_declaradas', 10, 2)->default(0);
            $table->string('estado', 20)->default('abierto');
            $table->dateTime('inicio');
            $table->dateTime('fin')->nullable();
            $table->string('cierre_uuid_cliente', 36)->nullable();
            $table->decimal('litros_sobrante', 10, 2)->nullable();
            $table->foreignId('imagen_campo_evidencia_id')->nullable()->constrained('ope_evidencias')->nullOnDelete();
            $table->foreignId('equipo_trabajo_id')->nullable()->constrained('per_equipos_trabajo')->restrictOnDelete();
            $table->foreignId('orden_trabajo_id')->nullable()->constrained('ope_ordenes_trabajo')->restrictOnDelete();
            $table->string('turno', 20)->nullable();
            $table->time('turno_hora_inicio')->nullable();
            $table->time('turno_hora_fin')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['equipo_trabajo_id'], 'ope_trabajos_equipo_trabajo_id_index');
            $table->index(['lote_id', 'estado'], 'ope_trabajos_lote_id_estado_index');
            $table->index(['orden_id'], 'ope_trabajos_orden_id_index');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_trabajos_cierre_uuid_cliente_unico ON {$prefijo}ope_trabajos USING btree (cierre_uuid_cliente) WHERE (cierre_uuid_cliente IS NOT NULL) AND (deleted_at IS NULL)
        SQL);

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_trabajos_imagen_campo_evidencia_id_unico ON {$prefijo}ope_trabajos USING btree (imagen_campo_evidencia_id) WHERE (imagen_campo_evidencia_id IS NOT NULL) AND (deleted_at IS NULL)
        SQL);

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_trabajos_uuid_cliente_unico ON {$prefijo}ope_trabajos USING btree (uuid_cliente) WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_trabajos
                ADD CONSTRAINT {$prefijo}ope_trabajos_estado_chk CHECK (estado IN ('abierto', 'cerrado')),
                ADD CONSTRAINT {$prefijo}ope_trabajos_hectareas_declaradas_chk CHECK (hectareas_declaradas >= 0),
                ADD CONSTRAINT {$prefijo}ope_trabajos_litros_sobrante_chk CHECK ((litros_sobrante IS NULL) OR (litros_sobrante >= 0)),
                ADD CONSTRAINT {$prefijo}ope_trabajos_nro_chk CHECK (nro_aplicacion >= 1),
                ADD CONSTRAINT {$prefijo}ope_trabajos_turno_chk CHECK ((turno IS NULL) OR turno IN ('manana', 'noche', 'todo_el_dia'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ope_trabajos');
    }
};
