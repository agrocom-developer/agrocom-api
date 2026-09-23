<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `man_vehiculos` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo MAN.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000045_create_man_vehiculos_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('man_vehiculos')) {
            return;
        }

        Schema::create('man_vehiculos', function (Blueprint $table) {
            $table->id();
            $table->string('identificador', 40);
            $table->foreignId('base_id')->nullable()->constrained('per_bases')->restrictOnDelete();
            $table->string('estado', 20)->default('activo');
            $table->string('marca', 60)->nullable();
            $table->string('modelo', 60)->nullable();
            $table->smallInteger('anio')->nullable();
            $table->string('combustible', 20)->nullable();
            $table->boolean('es_4x4')->default(false);
            $table->decimal('kilometraje_inicial', 10, 2)->nullable();
            $table->decimal('kilometraje_actual', 10, 2)->nullable();
            $table->string('tipo', 20)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['base_id'], 'man_vehiculos_base_id_index');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}man_vehiculos_identificador_unico ON {$prefijo}man_vehiculos USING btree (identificador) WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}man_vehiculos
                ADD CONSTRAINT {$prefijo}man_vehiculos_combustible_chk CHECK ((combustible IS NULL) OR combustible IN ('gasolina', 'diesel')),
                ADD CONSTRAINT {$prefijo}man_vehiculos_estado_chk CHECK (estado IN ('activo', 'taller', 'de_baja', 'pausa')),
                ADD CONSTRAINT {$prefijo}man_vehiculos_kilometraje_actual_chk CHECK (kilometraje_actual >= 0),
                ADD CONSTRAINT {$prefijo}man_vehiculos_kilometraje_inicial_chk CHECK (kilometraje_inicial >= 0),
                ADD CONSTRAINT {$prefijo}man_vehiculos_tipo_chk CHECK ((tipo IS NULL) OR tipo IN ('camioneta', 'camion', 'moto', 'chata'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('man_vehiculos');
    }
};
