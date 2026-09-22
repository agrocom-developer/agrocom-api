<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `ope_reportes_tecnicos` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo OPE.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000059_create_ope_reportes_tecnicos_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ope_reportes_tecnicos')) {
            return;
        }

        Schema::create('ope_reportes_tecnicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabajo_id')->constrained('ope_trabajos')->restrictOnDelete();
            $table->dateTime('hora_inicio')->nullable();
            $table->dateTime('hora_fin')->nullable();
            $table->string('pdf_path')->nullable();
            $table->dateTime('generado_en');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['trabajo_id'], 'ope_reportes_tecnicos_trabajo_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ope_reportes_tecnicos');
    }
};
