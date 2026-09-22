<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `per_equipo_integrantes` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo PER.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000064_create_per_equipo_integrantes_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('per_equipo_integrantes')) {
            return;
        }

        Schema::create('per_equipo_integrantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipo_trabajo_id')->constrained('per_equipos_trabajo')->restrictOnDelete();
            $table->foreignId('persona_id')->constrained('per_personas')->restrictOnDelete();
            $table->string('rol_equipo', 20);
            $table->date('desde');
            $table->date('hasta')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['equipo_trabajo_id'], 'per_equipo_integrantes_equipo_trabajo_id_index');
            $table->index(['persona_id'], 'per_equipo_integrantes_persona_id_index');
        });

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}per_equipo_integrantes
                ADD CONSTRAINT {$prefijo}per_equipo_integrantes_fechas_chk CHECK ((hasta IS NULL) OR (hasta >= desde)),
                ADD CONSTRAINT {$prefijo}per_equipo_integrantes_rol_chk CHECK (rol_equipo IN ('piloto', 'auxiliar'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('per_equipo_integrantes');
    }
};
