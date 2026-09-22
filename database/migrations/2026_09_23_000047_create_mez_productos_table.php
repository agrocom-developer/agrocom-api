<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `mez_productos` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo MEZ.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000047_create_mez_productos_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mez_productos')) {
            return;
        }

        Schema::create('mez_productos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}mez_productos_nombre_unico ON {$prefijo}mez_productos USING btree (nombre) WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('mez_productos');
    }
};
