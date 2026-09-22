<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `inv_repuestos` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo INV.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000038_create_inv_repuestos_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inv_repuestos')) {
            return;
        }

        Schema::create('inv_repuestos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 60);
            $table->string('descripcion');
            $table->string('unidad', 30);
            $table->decimal('costo_unitario', 12, 2)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}inv_repuestos_codigo_unico ON {$prefijo}inv_repuestos USING btree (codigo) WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}inv_repuestos
                ADD CONSTRAINT {$prefijo}inv_repuestos_costo_unitario_chk CHECK ((costo_unitario IS NULL) OR (costo_unitario >= 0))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_repuestos');
    }
};
