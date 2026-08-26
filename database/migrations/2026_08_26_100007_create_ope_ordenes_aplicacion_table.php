<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Operaciones — órdenes de aplicación (espec §4.3), con:
 *
 * - Parámetros de vuelo acordados (insumos §4): altura, velocidad y ancho de
 *   pasada — hoy se definen a voz entre piloto y agrónomo y no quedan en la
 *   orden. Velocidad en km/h, la unidad del negocio (caso real "≤ 15 km/h"
 *   y el HUD del RC la muestra en km/h — analisis_capturas_rc §2).
 * - Límites por orden (RF-60): NULL = hereda del contrato o del parámetro
 *   por defecto del sistema.
 *
 * Sin uuid_cliente: la orden nace en el panel (la transcribe el jefe/encargado
 * con evidencia del original — insumos §5), no se sincroniza desde la app de
 * campo; la idempotencia por UUID aplica a trabajos/sesiones (Sprint 2).
 *
 * Máquina de estados (espec §5): emitida → vigente → consumida | vencida.
 * "Una única orden vigente por lote" se garantiza en la base con un índice
 * parcial (ADR 0001), no solo en la aplicación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ope_ordenes_aplicacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained('com_contratos')->restrictOnDelete();
            $table->foreignId('lote_id')->constrained('com_lotes')->restrictOnDelete();
            $table->unsignedSmallInteger('nro_aplicacion');
            $table->decimal('litros_ha', 8, 2);
            $table->decimal('humedad_min_pct', 5, 2)->nullable();

            // Límites por orden (RF-60). NULL = hereda del contrato / parámetro por defecto.
            $table->decimal('viento_max_kmh', 5, 2)->nullable();
            $table->decimal('temperatura_max_c', 5, 2)->nullable();
            $table->decimal('humedad_max_pct', 5, 2)->nullable();
            $table->decimal('velocidad_max_kmh', 5, 2)->nullable();

            // Parámetros de vuelo acordados entre piloto y agrónomo (insumos §4).
            $table->decimal('altura_vuelo_m', 5, 2)->nullable();
            $table->decimal('velocidad_vuelo_kmh', 5, 2)->nullable();
            $table->decimal('ancho_pasada_m', 5, 2)->nullable();

            $table->text('observaciones')->nullable();
            $table->foreignId('emitida_por_contacto_id')
                ->nullable()
                ->comment('Agrónomo del cliente que emite la orden (com_cliente_contactos)')
                ->constrained('com_cliente_contactos')
                ->restrictOnDelete();
            $table->date('fecha_emision');
            $table->string('estado', 20)->default('emitida');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('contrato_id');
            $table->index(['lote_id', 'estado']);
            $table->index('emitida_por_contacto_id');
        });

        $prefijo = DB::getTablePrefix();

        // Una única orden vigente por lote (índice parcial — ADR 0001):
        // sin orden vigente no se abre trabajo; con dos vigentes no se sabría cuál rige.
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_ordenes_aplicacion_lote_vigente_unico
            ON {$prefijo}ope_ordenes_aplicacion (lote_id)
            WHERE estado = 'vigente' AND deleted_at IS NULL
        SQL);

        // Rangos y enums en la base, no solo en la aplicación (ADR 0001).
        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_ordenes_aplicacion
                ADD CONSTRAINT {$prefijo}ope_ordenes_aplicacion_estado_chk
                    CHECK (estado IN ('emitida', 'vigente', 'consumida', 'vencida')),
                ADD CONSTRAINT {$prefijo}ope_ordenes_aplicacion_nro_chk
                    CHECK (nro_aplicacion >= 1),
                ADD CONSTRAINT {$prefijo}ope_ordenes_aplicacion_litros_ha_chk
                    CHECK (litros_ha > 0),
                ADD CONSTRAINT {$prefijo}ope_ordenes_aplicacion_humedad_min_chk
                    CHECK (humedad_min_pct IS NULL OR (humedad_min_pct >= 0 AND humedad_min_pct <= 100)),
                ADD CONSTRAINT {$prefijo}ope_ordenes_aplicacion_viento_max_chk
                    CHECK (viento_max_kmh IS NULL OR viento_max_kmh > 0),
                ADD CONSTRAINT {$prefijo}ope_ordenes_aplicacion_temperatura_max_chk
                    CHECK (temperatura_max_c IS NULL OR (temperatura_max_c > -10 AND temperatura_max_c < 60)),
                ADD CONSTRAINT {$prefijo}ope_ordenes_aplicacion_humedad_max_chk
                    CHECK (humedad_max_pct IS NULL OR (humedad_max_pct >= 0 AND humedad_max_pct <= 100)),
                ADD CONSTRAINT {$prefijo}ope_ordenes_aplicacion_humedad_rango_chk
                    CHECK (humedad_min_pct IS NULL OR humedad_max_pct IS NULL OR humedad_min_pct <= humedad_max_pct),
                ADD CONSTRAINT {$prefijo}ope_ordenes_aplicacion_velocidad_max_chk
                    CHECK (velocidad_max_kmh IS NULL OR velocidad_max_kmh > 0),
                ADD CONSTRAINT {$prefijo}ope_ordenes_aplicacion_altura_vuelo_chk
                    CHECK (altura_vuelo_m IS NULL OR altura_vuelo_m > 0),
                ADD CONSTRAINT {$prefijo}ope_ordenes_aplicacion_velocidad_vuelo_chk
                    CHECK (velocidad_vuelo_kmh IS NULL OR velocidad_vuelo_kmh > 0),
                ADD CONSTRAINT {$prefijo}ope_ordenes_aplicacion_ancho_pasada_chk
                    CHECK (ancho_pasada_m IS NULL OR ancho_pasada_m > 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ope_ordenes_aplicacion');
    }
};
