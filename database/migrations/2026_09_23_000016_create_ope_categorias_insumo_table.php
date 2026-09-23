<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `ope_categorias_insumo` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo OPE.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000016_create_ope_categorias_insumo_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ope_categorias_insumo')) {
            return;
        }

        Schema::create('ope_categorias_insumo', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80);
            $table->string('tipo_insumo', 10);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_categorias_insumo_nombre_unico ON {$prefijo}ope_categorias_insumo USING btree (nombre) WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_categorias_insumo
                ADD CONSTRAINT {$prefijo}ope_categorias_insumo_tipo_insumo_chk CHECK (tipo_insumo IN ('solido', 'liquido'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ope_categorias_insumo');
    }
};
