<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `com_contratos` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo COM.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000004_create_com_contratos_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('com_contratos')) {
            return;
        }

        Schema::create('com_contratos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('com_clientes')->restrictOnDelete();
            $table->decimal('hectareas_contratadas', 10, 2);
            $table->smallInteger('aplicaciones_previstas');
            $table->decimal('precio_ha', 12, 2);
            $table->decimal('monto_total', 12, 2);
            $table->decimal('adelanto_monto', 12, 2)->nullable();
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->string('estado', 20)->default('borrador');
            $table->foreignId('campania_id')->constrained('cpn_campanias')->restrictOnDelete();
            $table->boolean('brinda_alimentacion')->default(false);
            $table->boolean('brinda_hospedaje')->default(false);
            $table->boolean('brinda_combustible')->default(false);
            $table->text('observaciones_logistica')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['cliente_id'], 'com_contratos_cliente_id_index');
        });

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}com_contratos
                ADD CONSTRAINT {$prefijo}com_contratos_adelanto_monto_chk CHECK ((adelanto_monto IS NULL) OR (adelanto_monto >= 0)),
                ADD CONSTRAINT {$prefijo}com_contratos_aplicaciones_chk CHECK (aplicaciones_previstas >= 1),
                ADD CONSTRAINT {$prefijo}com_contratos_estado_chk CHECK (estado IN ('borrador', 'vigente', 'finalizado', 'cancelado', 'pausado', 'conflicto')),
                ADD CONSTRAINT {$prefijo}com_contratos_fechas_chk CHECK ((fecha_fin IS NULL) OR (fecha_fin >= fecha_inicio)),
                ADD CONSTRAINT {$prefijo}com_contratos_hectareas_chk CHECK (hectareas_contratadas > 0),
                ADD CONSTRAINT {$prefijo}com_contratos_monto_total_chk CHECK (monto_total >= 0),
                ADD CONSTRAINT {$prefijo}com_contratos_precio_ha_chk CHECK (precio_ha >= 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('com_contratos');
    }
};
