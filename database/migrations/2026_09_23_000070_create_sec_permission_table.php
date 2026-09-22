<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `sec_permission` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo SEC.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000070_create_sec_permission_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sec_permission')) {
            return;
        }

        Schema::create('sec_permission', function (Blueprint $table) {
            $table->id();
            $table->string('code', 150);
            $table->string('description', 200);
            $table->boolean('state')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['code'], 'sec_permission_code_unique');
        });

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}sec_permission
                ADD CONSTRAINT {$prefijo}sec_permission_code_chk CHECK (code ~ '^[a-z0-9_]+\.[a-z0-9_]+\.[a-z0-9_]+$')
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sec_permission');
    }
};
