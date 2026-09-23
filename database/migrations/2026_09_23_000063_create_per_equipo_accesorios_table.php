<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `per_equipo_accesorios` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo PER.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000063_create_per_equipo_accesorios_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('per_equipo_accesorios')) {
            return;
        }

        Schema::create('per_equipo_accesorios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipo_trabajo_id')->constrained('per_equipos_trabajo')->restrictOnDelete();
            $table->foreignId('accesorio_id')->constrained('per_accesorios')->restrictOnDelete();
            $table->integer('cantidad')->default(1);
            $table->string('observacion')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['accesorio_id'], 'per_equipo_accesorios_accesorio_id_index');
            $table->index(['equipo_trabajo_id'], 'per_equipo_accesorios_equipo_trabajo_id_index');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}per_equipo_accesorios_unico ON {$prefijo}per_equipo_accesorios USING btree (equipo_trabajo_id, accesorio_id) WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}per_equipo_accesorios
                ADD CONSTRAINT {$prefijo}per_equipo_accesorios_cantidad_chk CHECK (cantidad >= 1)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('per_equipo_accesorios');
    }
};
