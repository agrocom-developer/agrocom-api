<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `per_accesorios` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo PER.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000062_create_per_accesorios_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('per_accesorios')) {
            return;
        }

        Schema::create('per_accesorios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80);
            $table->boolean('activo')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}per_accesorios_nombre_unico ON {$prefijo}per_accesorios USING btree (lower((nombre)::text)) WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('per_accesorios');
    }
};
