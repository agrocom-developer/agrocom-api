<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `ope_sesion_rechazos` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo OPE.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000060_create_ope_sesion_rechazos_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ope_sesion_rechazos')) {
            return;
        }

        Schema::create('ope_sesion_rechazos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('anula_a_id')->constrained('ope_sesiones')->restrictOnDelete();
            $table->string('motivo', 500);
            $table->foreignId('rechazado_por')->constrained('per_personas')->restrictOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_sesion_rechazos_anula_a_id_unico ON {$prefijo}ope_sesion_rechazos USING btree (anula_a_id) WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_sesion_rechazos
                ADD CONSTRAINT {$prefijo}ope_sesion_rechazos_motivo_chk CHECK (motivo <> '')
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ope_sesion_rechazos');
    }
};
