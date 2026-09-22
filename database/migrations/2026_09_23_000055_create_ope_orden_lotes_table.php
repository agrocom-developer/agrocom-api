<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `ope_orden_lotes` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo OPE.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000055_create_ope_orden_lotes_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ope_orden_lotes')) {
            return;
        }

        Schema::create('ope_orden_lotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_id')->constrained('ope_ordenes_aplicacion')->cascadeOnDelete();
            $table->foreignId('lote_id')->constrained('com_lotes')->restrictOnDelete();
            $table->decimal('hectareas_solicitadas', 10, 2);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['lote_id'], 'ope_orden_lotes_lote_id_index');
            $table->index(['orden_id'], 'ope_orden_lotes_orden_id_index');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_orden_lotes_orden_lote_unico ON {$prefijo}ope_orden_lotes USING btree (orden_id, lote_id) WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_orden_lotes
                ADD CONSTRAINT {$prefijo}ope_orden_lotes_hectareas_chk CHECK (hectareas_solicitadas > 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ope_orden_lotes');
    }
};
