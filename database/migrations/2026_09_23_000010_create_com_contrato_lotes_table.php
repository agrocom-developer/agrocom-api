<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `com_contrato_lotes` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo COM.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000010_create_com_contrato_lotes_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('com_contrato_lotes')) {
            return;
        }

        Schema::create('com_contrato_lotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained('com_contratos')->restrictOnDelete();
            $table->foreignId('lote_id')->constrained('com_lotes')->restrictOnDelete();
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fin')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['contrato_id'], 'com_contrato_lotes_contrato_id_index');
            $table->index(['lote_id'], 'com_contrato_lotes_lote_id_index');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}com_contrato_lotes_contrato_lote_unico ON {$prefijo}com_contrato_lotes USING btree (contrato_id, lote_id) WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}com_contrato_lotes
                ADD CONSTRAINT {$prefijo}com_contrato_lotes_horario_chk CHECK (((hora_inicio IS NULL) AND (hora_fin IS NULL)) OR (hora_fin > hora_inicio))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('com_contrato_lotes');
    }
};
