<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `inv_stock` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo INV.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000040_create_inv_stock_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inv_stock')) {
            return;
        }

        Schema::create('inv_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repuesto_id')->constrained('inv_repuestos')->restrictOnDelete();
            $table->foreignId('base_id')->constrained('per_bases')->restrictOnDelete();
            $table->decimal('cantidad', 12, 2)->default(0);
            $table->decimal('stock_minimo', 12, 2)->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['repuesto_id', 'base_id'], 'inv_stock_repuesto_id_base_id_unique');
            $table->index(['base_id'], 'inv_stock_base_id_index');
        });

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}inv_stock
                ADD CONSTRAINT {$prefijo}inv_stock_cantidad_chk CHECK (cantidad >= 0),
                ADD CONSTRAINT {$prefijo}inv_stock_stock_minimo_chk CHECK (stock_minimo >= 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_stock');
    }
};
