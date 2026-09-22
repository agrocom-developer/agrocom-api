<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `sec_datos_fiscales` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo SEC.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000069_create_sec_datos_fiscales_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sec_datos_fiscales')) {
            return;
        }

        Schema::create('sec_datos_fiscales', function (Blueprint $table) {
            $table->id();
            $table->string('razon_social_fiscal');
            $table->string('nit', 50);
            $table->string('domicilio_fiscal');
            $table->string('actividad_economica');
            $table->text('leyenda_pie')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sec_datos_fiscales');
    }
};
