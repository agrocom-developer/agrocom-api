<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `man_baterias` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo MAN.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000041_create_man_baterias_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('man_baterias')) {
            return;
        }

        Schema::create('man_baterias', function (Blueprint $table) {
            $table->id();
            $table->string('identificador', 40);
            $table->integer('ciclos_acumulados')->default(0);
            $table->string('estado', 20)->default('activa');
            $table->foreignId('base_id')->nullable()->constrained('per_bases')->restrictOnDelete();
            $table->integer('ciclos_inicial')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['base_id'], 'man_baterias_base_id_index');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}man_baterias_identificador_unico ON {$prefijo}man_baterias USING btree (identificador) WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}man_baterias
                ADD CONSTRAINT {$prefijo}man_baterias_ciclos_acumulados_chk CHECK (ciclos_acumulados >= 0),
                ADD CONSTRAINT {$prefijo}man_baterias_ciclos_inicial_chk CHECK (ciclos_inicial >= 0),
                ADD CONSTRAINT {$prefijo}man_baterias_estado_chk CHECK (estado IN ('activa', 'retirada', 'mantenimiento'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('man_baterias');
    }
};
