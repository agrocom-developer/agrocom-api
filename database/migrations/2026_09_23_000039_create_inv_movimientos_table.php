<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `inv_movimientos` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo INV.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000039_create_inv_movimientos_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inv_movimientos')) {
            return;
        }

        Schema::create('inv_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repuesto_id')->constrained('inv_repuestos')->restrictOnDelete();
            $table->foreignId('base_id')->constrained('per_bases')->restrictOnDelete();
            $table->foreignId('base_destino_id')->nullable()->constrained('per_bases')->restrictOnDelete();
            $table->string('tipo', 20);
            $table->decimal('cantidad', 12, 2);
            $table->string('sentido', 20)->nullable();
            $table->decimal('costo_unitario', 12, 2)->nullable();
            $table->string('motivo')->nullable();
            $table->foreignId('orden_mantenimiento_id')->nullable()->constrained('man_ordenes_mantenimiento')->restrictOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['base_id'], 'inv_movimientos_base_id_index');
            $table->index(['repuesto_id'], 'inv_movimientos_repuesto_id_index');
        });

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}inv_movimientos
                ADD CONSTRAINT {$prefijo}inv_movimientos_cantidad_chk CHECK (cantidad > 0),
                ADD CONSTRAINT {$prefijo}inv_movimientos_costo_unitario_chk CHECK ((costo_unitario IS NULL) OR (costo_unitario >= 0)),
                ADD CONSTRAINT {$prefijo}inv_movimientos_sentido_chk CHECK ((sentido IS NULL) OR sentido IN ('incremento', 'decremento')),
                ADD CONSTRAINT {$prefijo}inv_movimientos_tipo_chk CHECK (tipo IN ('compra', 'salida', 'ajuste', 'traslado'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_movimientos');
    }
};
