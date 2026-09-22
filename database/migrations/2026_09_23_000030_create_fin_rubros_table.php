<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `fin_rubros` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo FIN.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000030_create_fin_rubros_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fin_rubros')) {
            return;
        }

        Schema::create('fin_rubros', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->decimal('presupuesto_bs_ha', 12, 2);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}fin_rubros_nombre_unico ON {$prefijo}fin_rubros USING btree (nombre) WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_rubros');
    }
};
