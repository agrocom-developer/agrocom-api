<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `mez_mezcla_detalles` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo MEZ.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000048_create_mez_mezcla_detalles_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mez_mezcla_detalles')) {
            return;
        }

        Schema::create('mez_mezcla_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mezcla_id')->constrained('mez_mezclas')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('mez_productos')->restrictOnDelete();
            $table->decimal('cantidad', 10, 2);
            $table->string('unidad', 10);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['mezcla_id'], 'mez_mezcla_detalles_mezcla_id_index');
        });

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}mez_mezcla_detalles
                ADD CONSTRAINT {$prefijo}mez_mezcla_detalles_cantidad_chk CHECK (cantidad > 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mez_mezcla_detalles');
    }
};
