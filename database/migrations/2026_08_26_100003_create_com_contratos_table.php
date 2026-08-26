<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Comercial — contratos (espec §4.1) + parámetros por contrato
 * (insumos §4, RF-60): límites de condiciones, velocidad máxima exigida y
 * umbral de reporte de avance como columnas (1:1 con el contrato); las
 * ventanas horarias, que son N por contrato, van en com_contrato_ventanas.
 *
 * Los límites son NULL cuando rige el parámetro por defecto del sistema
 * (insumos §7.1); el contrato solo los modula (caso real: velocidad ≤ 15 km/h).
 * Dinero DECIMAL(12,2), hectáreas DECIMAL(10,2) — jamás float (espec §5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('com_contratos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('com_clientes')->restrictOnDelete();
            $table->decimal('hectareas_contratadas', 10, 2);
            $table->unsignedSmallInteger('aplicaciones_previstas');
            $table->decimal('precio_ha', 12, 2);
            $table->decimal('monto_total', 12, 2)->comment('Recalculable: hectareas_contratadas × aplicaciones_previstas × precio_ha');
            $table->decimal('adelanto_monto', 12, 2)->nullable();
            $table->decimal('adelanto_pct', 5, 2)->nullable();
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->string('estado', 20)->default('borrador');

            // Parámetros por contrato (NULL = rige el valor por defecto del sistema).
            $table->decimal('viento_max_kmh', 5, 2)->nullable();
            $table->decimal('temperatura_max_c', 5, 2)->nullable();
            $table->decimal('humedad_min_pct', 5, 2)->nullable();
            $table->decimal('humedad_max_pct', 5, 2)->nullable();
            $table->decimal('velocidad_max_kmh', 5, 2)->nullable()->comment('Velocidad máxima de vuelo exigida por el cliente');
            $table->decimal('umbral_reporte_avance_ha', 10, 2)->nullable()->comment('Reporte de avance cada N hectáreas (~500 ha)');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('cliente_id');
        });

        // Rangos y enums en la base, no solo en la aplicación (ADR 0001).
        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}com_contratos
                ADD CONSTRAINT {$prefijo}com_contratos_estado_chk
                    CHECK (estado IN ('borrador', 'vigente', 'finalizado', 'cancelado')),
                ADD CONSTRAINT {$prefijo}com_contratos_hectareas_chk
                    CHECK (hectareas_contratadas > 0),
                ADD CONSTRAINT {$prefijo}com_contratos_aplicaciones_chk
                    CHECK (aplicaciones_previstas >= 1),
                ADD CONSTRAINT {$prefijo}com_contratos_precio_ha_chk
                    CHECK (precio_ha >= 0),
                ADD CONSTRAINT {$prefijo}com_contratos_monto_total_chk
                    CHECK (monto_total >= 0),
                ADD CONSTRAINT {$prefijo}com_contratos_adelanto_monto_chk
                    CHECK (adelanto_monto IS NULL OR adelanto_monto >= 0),
                ADD CONSTRAINT {$prefijo}com_contratos_adelanto_pct_chk
                    CHECK (adelanto_pct IS NULL OR (adelanto_pct >= 0 AND adelanto_pct <= 100)),
                ADD CONSTRAINT {$prefijo}com_contratos_fechas_chk
                    CHECK (fecha_fin IS NULL OR fecha_fin >= fecha_inicio),
                ADD CONSTRAINT {$prefijo}com_contratos_viento_max_chk
                    CHECK (viento_max_kmh IS NULL OR viento_max_kmh > 0),
                ADD CONSTRAINT {$prefijo}com_contratos_temperatura_max_chk
                    CHECK (temperatura_max_c IS NULL OR (temperatura_max_c > -10 AND temperatura_max_c < 60)),
                ADD CONSTRAINT {$prefijo}com_contratos_humedad_min_chk
                    CHECK (humedad_min_pct IS NULL OR (humedad_min_pct >= 0 AND humedad_min_pct <= 100)),
                ADD CONSTRAINT {$prefijo}com_contratos_humedad_max_chk
                    CHECK (humedad_max_pct IS NULL OR (humedad_max_pct >= 0 AND humedad_max_pct <= 100)),
                ADD CONSTRAINT {$prefijo}com_contratos_humedad_rango_chk
                    CHECK (humedad_min_pct IS NULL OR humedad_max_pct IS NULL OR humedad_min_pct <= humedad_max_pct),
                ADD CONSTRAINT {$prefijo}com_contratos_velocidad_max_chk
                    CHECK (velocidad_max_kmh IS NULL OR velocidad_max_kmh > 0),
                ADD CONSTRAINT {$prefijo}com_contratos_umbral_reporte_chk
                    CHECK (umbral_reporte_avance_ha IS NULL OR umbral_reporte_avance_ha > 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('com_contratos');
    }
};
