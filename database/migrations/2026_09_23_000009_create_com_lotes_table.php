<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `com_lotes` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo COM.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000009_create_com_lotes_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('com_lotes')) {
            return;
        }

        Schema::create('com_lotes', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50);
            $table->decimal('hectareas', 10, 2);
            $table->jsonb('geometria')->nullable();
            $table->text('restricciones')->nullable();
            $table->string('desnivel', 20)->nullable();
            $table->string('limpieza', 20)->nullable();
            $table->foreignId('propiedad_id')->constrained('com_propiedades')->restrictOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['propiedad_id'], 'com_lotes_propiedad_id_index');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}com_lotes_codigo_unico ON {$prefijo}com_lotes USING btree (propiedad_id, codigo) WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}com_lotes
                ADD CONSTRAINT {$prefijo}com_lotes_desnivel_chk CHECK ((desnivel IS NULL) OR desnivel IN ('ninguno', 'algunos', 'varios', 'empinado')),
                ADD CONSTRAINT {$prefijo}com_lotes_hectareas_chk CHECK (hectareas > 0),
                ADD CONSTRAINT {$prefijo}com_lotes_limpieza_chk CHECK ((limpieza IS NULL) OR limpieza IN ('limpio', 'pocos_obstaculos', 'algunos_obstaculos', 'muchos_obstaculos'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('com_lotes');
    }
};
