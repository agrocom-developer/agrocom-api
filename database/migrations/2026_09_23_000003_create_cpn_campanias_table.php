<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `cpn_campanias` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo CPN.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000003_create_cpn_campanias_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cpn_campanias')) {
            return;
        }

        Schema::create('cpn_campanias', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20);
            $table->string('nombre', 150)->nullable();
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->string('estado', 20)->default('planificada');
            $table->string('estacion', 10);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}cpn_campanias_codigo_unico ON {$prefijo}cpn_campanias USING btree (codigo) WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}cpn_campanias
                ADD CONSTRAINT {$prefijo}cpn_campanias_estacion_chk CHECK (estacion IN ('invierno', 'verano')),
                ADD CONSTRAINT {$prefijo}cpn_campanias_estado_chk CHECK (estado IN ('planificada', 'abierta', 'cerrada')),
                ADD CONSTRAINT {$prefijo}cpn_campanias_fechas_chk CHECK (fecha_fin >= fecha_inicio)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cpn_campanias');
    }
};
