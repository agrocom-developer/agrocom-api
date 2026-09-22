<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `fin_tarifas` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo FIN.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000036_create_fin_tarifas_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fin_tarifas')) {
            return;
        }

        Schema::create('fin_tarifas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80);
            $table->string('modalidad', 10);
            $table->decimal('monto_piloto', 12, 2);
            $table->decimal('monto_auxiliar', 12, 2);
            $table->boolean('predeterminada')->default(false);
            $table->string('descripcion')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}fin_tarifas_nombre_unico ON {$prefijo}fin_tarifas USING btree (lower((nombre)::text)) WHERE deleted_at IS NULL
        SQL);

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}fin_tarifas_predeterminada_unica ON {$prefijo}fin_tarifas USING btree (predeterminada) WHERE predeterminada AND (deleted_at IS NULL)
        SQL);

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}fin_tarifas
                ADD CONSTRAINT {$prefijo}fin_tarifas_modalidad_chk CHECK (modalidad IN ('por_dia', 'por_ha')),
                ADD CONSTRAINT {$prefijo}fin_tarifas_monto_auxiliar_chk CHECK (monto_auxiliar >= 0),
                ADD CONSTRAINT {$prefijo}fin_tarifas_monto_piloto_chk CHECK (monto_piloto >= 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_tarifas');
    }
};
