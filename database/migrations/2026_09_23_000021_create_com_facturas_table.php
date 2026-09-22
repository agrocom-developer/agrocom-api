<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `com_facturas` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo COM.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000021_create_com_facturas_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('com_facturas')) {
            return;
        }

        Schema::create('com_facturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained('com_contratos')->restrictOnDelete();
            $table->foreignId('acta_id')->constrained('ope_actas')->restrictOnDelete();
            $table->decimal('hectareas_facturadas', 10, 2);
            $table->decimal('precio_ha', 10, 2);
            $table->decimal('monto', 12, 2);
            $table->date('fecha_emision');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['contrato_id'], 'com_facturas_contrato_id_index');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}com_facturas_acta_unico ON {$prefijo}com_facturas USING btree (acta_id) WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}com_facturas
                ADD CONSTRAINT {$prefijo}com_facturas_hectareas_facturadas_chk CHECK (hectareas_facturadas >= 0),
                ADD CONSTRAINT {$prefijo}com_facturas_monto_chk CHECK (monto >= 0),
                ADD CONSTRAINT {$prefijo}com_facturas_precio_ha_chk CHECK (precio_ha >= 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('com_facturas');
    }
};
