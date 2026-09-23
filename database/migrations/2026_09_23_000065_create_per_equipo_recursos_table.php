<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `per_equipo_recursos` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo PER.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000065_create_per_equipo_recursos_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('per_equipo_recursos')) {
            return;
        }

        Schema::create('per_equipo_recursos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipo_trabajo_id')->constrained('per_equipos_trabajo')->restrictOnDelete();
            $table->string('recurso_tipo', 20);
            $table->unsignedBigInteger('recurso_id');
            $table->date('desde');
            $table->date('hasta')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['equipo_trabajo_id'], 'per_equipo_recursos_equipo_trabajo_id_index');
            $table->index(['recurso_tipo', 'recurso_id'], 'per_equipo_recursos_recurso_tipo_recurso_id_index');
        });

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}per_equipo_recursos
                ADD CONSTRAINT {$prefijo}per_equipo_recursos_fechas_chk CHECK ((hasta IS NULL) OR (hasta >= desde)),
                ADD CONSTRAINT {$prefijo}per_equipo_recursos_tipo_chk CHECK (recurso_tipo IN ('dron', 'vehiculo', 'generador', 'bateria'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('per_equipo_recursos');
    }
};
