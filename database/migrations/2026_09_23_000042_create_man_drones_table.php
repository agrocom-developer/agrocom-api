<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `man_drones` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo MAN.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000042_create_man_drones_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('man_drones')) {
            return;
        }

        Schema::create('man_drones', function (Blueprint $table) {
            $table->id();
            $table->string('identificador_dron', 40);
            $table->string('numero_serie')->nullable();
            $table->string('chasis')->nullable();
            $table->string('version_software')->nullable();
            $table->string('region')->nullable();
            $table->string('serie_control')->nullable();
            $table->boolean('tiene_cargador_control')->default(false);
            $table->boolean('tiene_modem')->default(false);
            $table->boolean('tiene_maletin')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}man_drones_identificador_dron_unico ON {$prefijo}man_drones USING btree (identificador_dron) WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('man_drones');
    }
};
