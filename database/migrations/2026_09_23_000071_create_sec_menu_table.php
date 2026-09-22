<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `sec_menu` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo SEC.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000071_create_sec_menu_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sec_menu')) {
            return;
        }

        Schema::create('sec_menu', function (Blueprint $table) {
            $table->id();
            $table->string('label', 150);
            $table->string('icono', 60);
            $table->string('ruta', 150)->nullable();
            $table->foreignId('padre_id')->nullable()->constrained('sec_menu')->restrictOnDelete();
            $table->integer('orden')->default(0);
            $table->foreignId('permission_id')->nullable()->constrained('sec_permission')->restrictOnDelete();
            $table->string('descripcion', 150)->nullable();
            $table->boolean('requiere_persona')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['padre_id'], 'sec_menu_padre_id_index');
            $table->index(['permission_id'], 'sec_menu_permission_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sec_menu');
    }
};
