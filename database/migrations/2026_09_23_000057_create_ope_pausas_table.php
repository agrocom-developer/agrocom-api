<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `ope_pausas` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo OPE.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000057_create_ope_pausas_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ope_pausas')) {
            return;
        }

        Schema::create('ope_pausas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sesion_id')->constrained('ope_sesiones')->restrictOnDelete();
            $table->string('causa', 30);
            $table->dateTime('inicio');
            $table->dateTime('fin');
            $table->integer('duracion_minutos');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['causa'], 'ope_pausas_causa_index');
            $table->index(['sesion_id'], 'ope_pausas_sesion_id_index');
        });

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_pausas
                ADD CONSTRAINT {$prefijo}ope_pausas_causa_chk CHECK (causa IN ('clima', 'imprevisto_del_cliente', 'cambio_lote_cliente', 'falla_equipo', 'logistica')),
                ADD CONSTRAINT {$prefijo}ope_pausas_duracion_minutos_chk CHECK (duracion_minutos > 0),
                ADD CONSTRAINT {$prefijo}ope_pausas_fin_posterior_a_inicio_chk CHECK (fin > inicio)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ope_pausas');
    }
};
