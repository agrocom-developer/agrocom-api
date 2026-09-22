<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `fin_planilla_detalles` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo FIN.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000035_create_fin_planilla_detalles_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fin_planilla_detalles')) {
            return;
        }

        Schema::create('fin_planilla_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planilla_id')->constrained('fin_planillas')->cascadeOnDelete();
            $table->foreignId('persona_id')->constrained('per_personas')->restrictOnDelete();
            $table->decimal('devengado', 12, 2);
            $table->decimal('anticipos', 12, 2);
            $table->decimal('neto', 12, 2);
            $table->string('pdf_path')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['planilla_id', 'persona_id'], 'fin_planilla_detalles_planilla_id_persona_id_unique');
        });

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}fin_planilla_detalles
                ADD CONSTRAINT {$prefijo}fin_planilla_detalles_anticipos_chk CHECK (anticipos >= 0),
                ADD CONSTRAINT {$prefijo}fin_planilla_detalles_devengado_chk CHECK (devengado >= 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_planilla_detalles');
    }
};
