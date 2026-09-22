<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `ope_ordenes_aplicacion` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo OPE.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000017_create_ope_ordenes_aplicacion_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ope_ordenes_aplicacion')) {
            return;
        }

        Schema::create('ope_ordenes_aplicacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained('com_contratos')->restrictOnDelete();
            $table->smallInteger('nro_aplicacion');
            $table->string('tipo_aplicacion', 20)->default('desarrollo');
            $table->text('observaciones')->nullable();
            $table->foreignId('emitida_por_contacto_id')->nullable()->constrained('com_cliente_contactos')->restrictOnDelete();
            $table->date('fecha_emision');
            $table->string('estado', 20)->default('emitida');
            $table->smallInteger('cantidad_equipos_necesarios')->default(1);
            $table->foreignId('categoria_insumo_id')->nullable()->constrained('ope_categorias_insumo')->restrictOnDelete();
            $table->decimal('kilos_por_vuelo', 8, 2)->nullable();
            $table->decimal('litros_ha', 8, 2)->nullable();
            $table->text('motivo_pausa')->nullable();
            $table->dateTime('pausada_at')->nullable();
            $table->dateTime('reanudada_at')->nullable();
            $table->dateTime('cerrada_at')->nullable();
            $table->dateTime('cancelada_at')->nullable();
            $table->string('causa_cancelacion', 20)->nullable();
            $table->text('motivo_cancelacion')->nullable();
            $table->text('motivo_correccion')->nullable();
            $table->dateTime('corregida_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['contrato_id'], 'ope_ordenes_aplicacion_contrato_id_index');
            $table->index(['emitida_por_contacto_id'], 'ope_ordenes_aplicacion_emitida_por_contacto_id_index');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_ordenes_aplicacion_nro_por_contrato ON {$prefijo}ope_ordenes_aplicacion USING btree (contrato_id, nro_aplicacion) WHERE (deleted_at IS NULL) AND (NOT ((estado = 'cancelada') AND (causa_cancelacion = 'factor_externo')))
        SQL);

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_ordenes_aplicacion_una_abierta_por_contrato ON {$prefijo}ope_ordenes_aplicacion USING btree (contrato_id) WHERE (deleted_at IS NULL) AND estado IN ('emitida', 'vigente', 'pausada')
        SQL);

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_ordenes_aplicacion
                ADD CONSTRAINT {$prefijo}ope_ordenes_aplicacion_cancelada_con_causa_chk CHECK ((estado <> 'cancelada') OR ((causa_cancelacion IS NOT NULL) AND (motivo_cancelacion IS NOT NULL))),
                ADD CONSTRAINT {$prefijo}ope_ordenes_aplicacion_cantidad_equipos_chk CHECK (cantidad_equipos_necesarios >= 1),
                ADD CONSTRAINT {$prefijo}ope_ordenes_aplicacion_causa_cancelacion_chk CHECK ((causa_cancelacion IS NULL) OR causa_cancelacion IN ('cliente', 'dueno', 'factor_externo')),
                ADD CONSTRAINT {$prefijo}ope_ordenes_aplicacion_estado_chk CHECK (estado IN ('emitida', 'vigente', 'pausada', 'consumida', 'cancelada', 'vencida')),
                ADD CONSTRAINT {$prefijo}ope_ordenes_aplicacion_kilos_por_vuelo_chk CHECK ((kilos_por_vuelo IS NULL) OR (kilos_por_vuelo > 0)),
                ADD CONSTRAINT {$prefijo}ope_ordenes_aplicacion_litros_ha_chk CHECK ((litros_ha IS NULL) OR (litros_ha > 0)),
                ADD CONSTRAINT {$prefijo}ope_ordenes_aplicacion_nro_chk CHECK (nro_aplicacion >= 1),
                ADD CONSTRAINT {$prefijo}ope_ordenes_aplicacion_tipo_aplicacion_chk CHECK (tipo_aplicacion IN ('siembra', 'desarrollo', 'cosecha'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ope_ordenes_aplicacion');
    }
};
