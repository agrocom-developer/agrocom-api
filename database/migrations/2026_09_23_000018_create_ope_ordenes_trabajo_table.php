<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `ope_ordenes_trabajo` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo OPE.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000018_create_ope_ordenes_trabajo_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ope_ordenes_trabajo')) {
            return;
        }

        Schema::create('ope_ordenes_trabajo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_id')->constrained('ope_ordenes_aplicacion')->restrictOnDelete();
            $table->smallInteger('nro_aplicacion');
            $table->decimal('humedad_min_pct', 5, 2)->nullable();
            $table->decimal('viento_max_kmh', 5, 2)->nullable();
            $table->decimal('temperatura_max_c', 5, 2)->nullable();
            $table->decimal('humedad_max_pct', 5, 2)->nullable();
            $table->decimal('altura_vuelo_m', 5, 2)->nullable();
            $table->decimal('velocidad_vuelo_kmh', 5, 2)->nullable();
            $table->decimal('ancho_pasada_m', 5, 2)->nullable();
            $table->decimal('ph_agua', 4, 2)->nullable();
            $table->decimal('ph_calda', 4, 2)->nullable();
            $table->decimal('litros_ha', 8, 2)->nullable();
            $table->decimal('kilos_ha', 8, 2)->nullable();
            $table->jsonb('calda_productos')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['orden_id'], 'ope_ordenes_trabajo_orden_id_index');
        });

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_ordenes_trabajo
                ADD CONSTRAINT {$prefijo}ope_ordenes_trabajo_altura_vuelo_chk CHECK ((altura_vuelo_m IS NULL) OR (altura_vuelo_m > 0)),
                ADD CONSTRAINT {$prefijo}ope_ordenes_trabajo_ancho_pasada_chk CHECK ((ancho_pasada_m IS NULL) OR (ancho_pasada_m > 0)),
                ADD CONSTRAINT {$prefijo}ope_ordenes_trabajo_humedad_max_chk CHECK ((humedad_max_pct IS NULL) OR ((humedad_max_pct >= 0) AND (humedad_max_pct <= 100))),
                ADD CONSTRAINT {$prefijo}ope_ordenes_trabajo_humedad_min_chk CHECK ((humedad_min_pct IS NULL) OR ((humedad_min_pct >= 0) AND (humedad_min_pct <= 100))),
                ADD CONSTRAINT {$prefijo}ope_ordenes_trabajo_humedad_rango_chk CHECK ((humedad_min_pct IS NULL) OR (humedad_max_pct IS NULL) OR (humedad_min_pct <= humedad_max_pct)),
                ADD CONSTRAINT {$prefijo}ope_ordenes_trabajo_kilos_ha_chk CHECK ((kilos_ha IS NULL) OR (kilos_ha > 0)),
                ADD CONSTRAINT {$prefijo}ope_ordenes_trabajo_litros_ha_chk CHECK ((litros_ha IS NULL) OR (litros_ha > 0)),
                ADD CONSTRAINT {$prefijo}ope_ordenes_trabajo_nro_chk CHECK (nro_aplicacion >= 1),
                ADD CONSTRAINT {$prefijo}ope_ordenes_trabajo_ph_agua_chk CHECK ((ph_agua IS NULL) OR ((ph_agua >= 0) AND (ph_agua <= 14))),
                ADD CONSTRAINT {$prefijo}ope_ordenes_trabajo_ph_calda_chk CHECK ((ph_calda IS NULL) OR ((ph_calda >= 0) AND (ph_calda <= 14))),
                ADD CONSTRAINT {$prefijo}ope_ordenes_trabajo_temperatura_max_chk CHECK ((temperatura_max_c IS NULL) OR ((temperatura_max_c > -10) AND (temperatura_max_c < 60))),
                ADD CONSTRAINT {$prefijo}ope_ordenes_trabajo_velocidad_vuelo_chk CHECK ((velocidad_vuelo_kmh IS NULL) OR (velocidad_vuelo_kmh > 0)),
                ADD CONSTRAINT {$prefijo}ope_ordenes_trabajo_viento_max_chk CHECK ((viento_max_kmh IS NULL) OR (viento_max_kmh > 0))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ope_ordenes_trabajo');
    }
};
