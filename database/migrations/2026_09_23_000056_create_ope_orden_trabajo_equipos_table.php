<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `ope_orden_trabajo_equipos` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo OPE.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000056_create_ope_orden_trabajo_equipos_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ope_orden_trabajo_equipos')) {
            return;
        }

        Schema::create('ope_orden_trabajo_equipos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_trabajo_id')->constrained('ope_ordenes_trabajo')->restrictOnDelete();
            $table->foreignId('equipo_trabajo_id')->constrained('per_equipos_trabajo')->restrictOnDelete();
            $table->foreignId('tarifa_id')->nullable()->constrained('fin_tarifas')->restrictOnDelete();
            $table->string('modalidad_pago', 10);
            $table->decimal('monto_piloto', 12, 2);
            $table->decimal('monto_auxiliar', 12, 2);
            $table->boolean('negociado')->default(false);
            $table->string('motivo_negociacion')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['equipo_trabajo_id'], 'ope_orden_trabajo_equipos_equipo_trabajo_id_index');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_orden_trabajo_equipos_unico ON {$prefijo}ope_orden_trabajo_equipos USING btree (orden_trabajo_id, equipo_trabajo_id) WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_orden_trabajo_equipos
                ADD CONSTRAINT {$prefijo}ope_orden_trabajo_equipos_modalidad_chk CHECK (modalidad_pago IN ('por_dia', 'por_ha')),
                ADD CONSTRAINT {$prefijo}ope_orden_trabajo_equipos_monto_auxiliar_chk CHECK (monto_auxiliar >= 0),
                ADD CONSTRAINT {$prefijo}ope_orden_trabajo_equipos_monto_piloto_chk CHECK (monto_piloto >= 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ope_orden_trabajo_equipos');
    }
};
