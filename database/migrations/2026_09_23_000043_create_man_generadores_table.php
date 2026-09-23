<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `man_generadores` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo MAN.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000043_create_man_generadores_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('man_generadores')) {
            return;
        }

        Schema::create('man_generadores', function (Blueprint $table) {
            $table->id();
            $table->string('identificador', 40);
            $table->string('modelo', 60)->nullable();
            $table->foreignId('base_id')->nullable()->constrained('per_bases')->restrictOnDelete();
            $table->string('estado', 20)->default('activo');
            $table->decimal('horas_inicial', 8, 2)->nullable();
            $table->decimal('horas_actual', 8, 2)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['base_id'], 'man_generadores_base_id_index');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}man_generadores_identificador_unico ON {$prefijo}man_generadores USING btree (identificador) WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}man_generadores
                ADD CONSTRAINT {$prefijo}man_generadores_estado_chk CHECK (estado IN ('activo', 'taller', 'de_baja')),
                ADD CONSTRAINT {$prefijo}man_generadores_horas_actual_chk CHECK ((horas_actual IS NULL) OR (horas_actual >= 0)),
                ADD CONSTRAINT {$prefijo}man_generadores_horas_actual_ge_inicial_chk CHECK ((horas_actual IS NULL) OR (horas_inicial IS NULL) OR (horas_actual >= horas_inicial)),
                ADD CONSTRAINT {$prefijo}man_generadores_horas_inicial_chk CHECK ((horas_inicial IS NULL) OR (horas_inicial >= 0))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('man_generadores');
    }
};
