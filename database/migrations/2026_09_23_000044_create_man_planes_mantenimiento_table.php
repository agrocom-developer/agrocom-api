<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `man_planes_mantenimiento` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo MAN.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000044_create_man_planes_mantenimiento_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('man_planes_mantenimiento')) {
            return;
        }

        Schema::create('man_planes_mantenimiento', function (Blueprint $table) {
            $table->id();
            $table->string('modelo', 40);
            $table->text('tarea');
            $table->decimal('horas_umbral', 8, 2);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['modelo'], 'man_planes_mantenimiento_modelo_index');
        });

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}man_planes_mantenimiento
                ADD CONSTRAINT {$prefijo}man_planes_mantenimiento_horas_umbral_chk CHECK (horas_umbral > 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('man_planes_mantenimiento');
    }
};
