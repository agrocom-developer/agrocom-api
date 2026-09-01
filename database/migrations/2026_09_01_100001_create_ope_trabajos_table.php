<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Operaciones — trabajo (espec §4.3, TE-05). Primera tabla que nace en
 * la app de campo y sincroniza por lote (espec §2.1): trae su `uuid_cliente`
 * generado en el dispositivo, y el `UNIQUE` parcial de abajo es el mecanismo
 * real de idempotencia (invariante 1 de CLAUDE.md) — nunca un chequeo previo
 * en código.
 *
 * Recorte de alcance de la tarea 09 (`docs/gestion/cola_tareas.md`):
 * `hectareas_validadas` y `motivo_observacion` de la espec quedan fuera hasta
 * HU-14 (validación) — sin ese flujo no hay quién las escriba. Solo dos
 * estados por ahora (`abierto`/`cerrado`): la apertura la trae este sync, el
 * cierre real con hectáreas llega con HU-05.
 *
 * `orden_id` y `lote_id` referencian tablas de otro módulo (`Operaciones` y
 * `Comercial` respectivamente) solo por FK + entero plano (ADR 0003, regla
 * 3) — nunca una relación Eloquent cruzada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ope_trabajos', function (Blueprint $table) {
            $table->id();
            $table->string('uuid_cliente', 36);
            $table->foreignId('orden_id')->constrained('ope_ordenes_aplicacion')->restrictOnDelete();
            $table->foreignId('lote_id')->constrained('com_lotes')->restrictOnDelete();
            $table->unsignedSmallInteger('nro_aplicacion');
            $table->decimal('hectareas_declaradas', 10, 2)->default(0);
            $table->string('estado', 20)->default('abierto');
            $table->dateTime('inicio');
            $table->dateTime('fin')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('orden_id');
            $table->index(['lote_id', 'estado']);
        });

        $prefijo = DB::getTablePrefix();

        // Idempotencia real (invariante 1): un reintento del mismo lote choca
        // acá, nunca con un SELECT previo en el caso de uso.
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_trabajos_uuid_cliente_unico
            ON {$prefijo}ope_trabajos (uuid_cliente)
            WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_trabajos
                ADD CONSTRAINT {$prefijo}ope_trabajos_estado_chk
                    CHECK (estado IN ('abierto', 'cerrado')),
                ADD CONSTRAINT {$prefijo}ope_trabajos_nro_chk
                    CHECK (nro_aplicacion >= 1),
                ADD CONSTRAINT {$prefijo}ope_trabajos_hectareas_declaradas_chk
                    CHECK (hectareas_declaradas >= 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ope_trabajos');
    }
};
