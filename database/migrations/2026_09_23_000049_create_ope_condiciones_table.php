<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `ope_condiciones` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo OPE.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000049_create_ope_condiciones_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ope_condiciones')) {
            return;
        }

        Schema::create('ope_condiciones', function (Blueprint $table) {
            $table->id();
            $table->string('uuid_cliente', 36);
            $table->foreignId('trabajo_id')->constrained('ope_trabajos')->restrictOnDelete();
            $table->foreignId('sesion_id')->constrained('ope_sesiones')->restrictOnDelete();
            $table->string('momento', 20)->default('inicio_sesion');
            $table->decimal('viento_kmh', 5, 2);
            $table->decimal('temperatura_c', 5, 2);
            $table->decimal('humedad_pct', 5, 2);
            $table->boolean('autorizado');
            $table->text('observacion_agronomo')->nullable();
            $table->string('firma_observacion')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['sesion_id'], 'ope_condiciones_sesion_id_index');
            $table->index(['trabajo_id', 'momento'], 'ope_condiciones_trabajo_id_momento_index');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_condiciones_uuid_cliente_unico ON {$prefijo}ope_condiciones USING btree (uuid_cliente) WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_condiciones
                ADD CONSTRAINT {$prefijo}ope_condiciones_humedad_chk CHECK ((humedad_pct >= 0) AND (humedad_pct <= 100)),
                ADD CONSTRAINT {$prefijo}ope_condiciones_momento_chk CHECK (momento = 'inicio_sesion'),
                ADD CONSTRAINT {$prefijo}ope_condiciones_observacion_si_no_autorizado_chk CHECK ((autorizado = true) OR ((observacion_agronomo IS NOT NULL) AND (firma_observacion IS NOT NULL))),
                ADD CONSTRAINT {$prefijo}ope_condiciones_temperatura_chk CHECK ((temperatura_c > -10) AND (temperatura_c < 60)),
                ADD CONSTRAINT {$prefijo}ope_condiciones_viento_chk CHECK (viento_kmh >= 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ope_condiciones');
    }
};
