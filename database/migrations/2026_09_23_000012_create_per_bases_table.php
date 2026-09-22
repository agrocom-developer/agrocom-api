<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `per_bases` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo PER.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000012_create_per_bases_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('per_bases')) {
            return;
        }

        Schema::create('per_bases', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('ubicacion', 200)->nullable();
            $table->decimal('latitud', 9, 6)->nullable();
            $table->decimal('longitud', 9, 6)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}per_bases
                ADD CONSTRAINT {$prefijo}per_bases_latitud_chk CHECK ((latitud IS NULL) OR ((latitud >= -90) AND (latitud <= 90))),
                ADD CONSTRAINT {$prefijo}per_bases_longitud_chk CHECK ((longitud IS NULL) OR ((longitud >= -180) AND (longitud <= 180)))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('per_bases');
    }
};
