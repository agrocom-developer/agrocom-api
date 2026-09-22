<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `man_ordenes_mantenimiento` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo MAN.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000037_create_man_ordenes_mantenimiento_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('man_ordenes_mantenimiento')) {
            return;
        }

        Schema::create('man_ordenes_mantenimiento', function (Blueprint $table) {
            $table->id();
            $table->string('equipo_tipo', 20);
            $table->unsignedBigInteger('equipo_id');
            $table->string('tipo', 20);
            $table->text('descripcion');
            $table->string('estado', 20);
            $table->dateTime('fecha_apertura');
            $table->dateTime('fecha_cierre')->nullable();
            $table->foreignId('gasto_id')->nullable()->constrained('fin_gastos')->restrictOnDelete();
            $table->text('descripcion_final')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['equipo_tipo', 'equipo_id'], 'man_ordenes_mantenimiento_equipo_tipo_equipo_id_index');
            $table->index(['estado'], 'man_ordenes_mantenimiento_estado_index');
        });

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}man_ordenes_mantenimiento
                ADD CONSTRAINT {$prefijo}man_ordenes_mantenimiento_equipo_tipo_chk CHECK (equipo_tipo IN ('dron', 'vehiculo')),
                ADD CONSTRAINT {$prefijo}man_ordenes_mantenimiento_estado_chk CHECK (estado IN ('abierta', 'cerrada')),
                ADD CONSTRAINT {$prefijo}man_ordenes_mantenimiento_tipo_chk CHECK (tipo IN ('preventivo', 'correctivo'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('man_ordenes_mantenimiento');
    }
};
