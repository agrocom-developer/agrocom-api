<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `sec_role` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo SEC.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000072_create_sec_role_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sec_role')) {
            return;
        }

        Schema::create('sec_role', function (Blueprint $table) {
            $table->id();
            $table->string('name', 30);
            $table->string('description', 150);
            $table->boolean('state')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['name'], 'sec_role_name_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sec_role');
    }
};
