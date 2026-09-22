<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `ope_drones` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo OPE.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000026_create_ope_drones_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ope_drones')) {
            return;
        }

        Schema::create('ope_drones', function (Blueprint $table) {
            $table->id();
            $table->string('identificador', 40);
            $table->string('modelo', 40)->nullable();
            $table->decimal('capacidad_l', 5, 2)->nullable();
            $table->decimal('capacidad_kg', 6, 2)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_drones_identificador_unico ON {$prefijo}ope_drones USING btree (identificador) WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_drones
                ADD CONSTRAINT {$prefijo}ope_drones_capacidad_l_chk CHECK ((capacidad_l IS NULL) OR capacidad_l IN (30, 50, 60))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ope_drones');
    }
};
