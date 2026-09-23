<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `sec_datos_empresa` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo SEC.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000068_create_sec_datos_empresa_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sec_datos_empresa')) {
            return;
        }

        Schema::create('sec_datos_empresa', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('rubro');
            $table->string('logo_path')->nullable();
            $table->string('email')->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('direccion')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sec_datos_empresa');
    }
};
