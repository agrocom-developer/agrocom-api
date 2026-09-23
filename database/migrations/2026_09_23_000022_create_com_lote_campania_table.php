<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `com_lote_campania` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo COM.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000022_create_com_lote_campania_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('com_lote_campania')) {
            return;
        }

        Schema::create('com_lote_campania', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lote_id')->constrained('com_lotes')->restrictOnDelete();
            $table->foreignId('campania_id')->constrained('cpn_campanias')->restrictOnDelete();
            $table->foreignId('cultivo_id')->constrained('com_cultivos')->restrictOnDelete();
            $table->decimal('hectareas_sembradas', 10, 2);
            $table->date('fecha_siembra')->nullable();
            $table->date('fecha_cosecha_estimada')->nullable();
            $table->string('etapa_cultivo', 20)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['campania_id'], 'com_lote_campania_campania_id_index');
            $table->index(['cultivo_id'], 'com_lote_campania_cultivo_id_index');
            $table->index(['lote_id'], 'com_lote_campania_lote_id_index');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}com_lote_campania_lote_campania_unico ON {$prefijo}com_lote_campania USING btree (lote_id, campania_id) WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}com_lote_campania
                ADD CONSTRAINT {$prefijo}com_lote_campania_etapa_cultivo_chk CHECK ((etapa_cultivo IS NULL) OR etapa_cultivo IN ('preparacion', 'germinacion', 'crecimiento', 'floracion', 'fructificacion', 'cosecha')),
                ADD CONSTRAINT {$prefijo}com_lote_campania_fechas_chk CHECK ((fecha_cosecha_estimada IS NULL) OR (fecha_siembra IS NULL) OR (fecha_cosecha_estimada >= fecha_siembra)),
                ADD CONSTRAINT {$prefijo}com_lote_campania_hectareas_chk CHECK (hectareas_sembradas > 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('com_lote_campania');
    }
};
