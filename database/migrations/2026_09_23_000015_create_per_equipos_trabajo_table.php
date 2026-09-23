<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `per_equipos_trabajo` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo PER.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000015_create_per_equipos_trabajo_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('per_equipos_trabajo')) {
            return;
        }

        Schema::create('per_equipos_trabajo', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20);
            $table->string('nombre', 150)->nullable();
            $table->foreignId('base_id')->constrained('per_bases')->restrictOnDelete();
            $table->string('estado', 20)->default('activo');
            $table->date('desde');
            $table->date('hasta')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['base_id'], 'per_equipos_trabajo_base_id_index');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}per_equipos_trabajo_codigo_unico ON {$prefijo}per_equipos_trabajo USING btree (codigo) WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}per_equipos_trabajo
                ADD CONSTRAINT {$prefijo}per_equipos_trabajo_estado_chk CHECK (estado IN ('activo', 'inactivo')),
                ADD CONSTRAINT {$prefijo}per_equipos_trabajo_fechas_chk CHECK ((hasta IS NULL) OR (hasta >= desde))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('per_equipos_trabajo');
    }
};
