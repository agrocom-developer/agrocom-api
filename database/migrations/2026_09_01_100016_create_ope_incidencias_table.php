<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Operaciones — incidencias de sesión (espec §4.3, fila `incidencias`;
 * HU-08, tarea 22). Nace en la app de campo con su `uuid_cliente` (invariante
 * 1), referencia la sesión por FK real (a diferencia del DTO de sync, que la
 * resuelve por `uuid_cliente`) — para cuando esta migración corre ya existe
 * `ope_sesiones`. Sin `trabajo_id`: a diferencia de `ope_condiciones`, la
 * espec (§4.3) no lista esa columna denormalizada para `incidencias`.
 *
 * `tipo`: catálogo cerrado de la espec (`caldo`, `esc`, `bateria`, `mecanica`,
 * `clima`, `otro`), `CHECK` en Postgres — mismo patrón que `momento` en
 * `ope_condiciones`.
 *
 * `evidencia_foto_id` referencia `ope_evidencias.id` con FK real (mismo
 * criterio que `imagen_campo_evidencia_id` en `ope_trabajos`: ambas tablas
 * viven en `Operaciones`, ADR 0003 regla 3 no aplica) y NOT NULL: el título
 * de la HU ("con foto") y su propósito ("respaldar el reporte") hacen que la
 * foto sea condición del registro, no un dato opcional (ver docblock de
 * `Contratos/RegistroIncidencia`).
 *
 * Índice único parcial sobre `evidencia_foto_id`, mismo motivo que
 * `ope_trabajos_imagen_campo_evidencia_id_unico` (tarea 21): sin él, la misma
 * foto podría "respaldar" dos incidencias distintas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ope_incidencias', function (Blueprint $table) {
            $table->id();
            $table->string('uuid_cliente', 36);
            $table->foreignId('sesion_id')->constrained('ope_sesiones')->restrictOnDelete();
            $table->string('tipo', 20);
            $table->text('descripcion')->nullable();
            $table->dateTime('hora');
            $table->foreignId('evidencia_foto_id')->constrained('ope_evidencias')->restrictOnDelete();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('sesion_id');
        });

        $prefijo = DB::getTablePrefix();

        // Idempotencia real (invariante 1): un reintento del mismo lote choca
        // acá, nunca con un SELECT previo en el caso de uso.
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_incidencias_uuid_cliente_unico
            ON {$prefijo}ope_incidencias (uuid_cliente)
            WHERE deleted_at IS NULL
        SQL);

        // Una misma foto no puede "respaldar" dos incidencias distintas
        // (mismo criterio que ope_trabajos_imagen_campo_evidencia_id_unico).
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_incidencias_evidencia_foto_id_unico
            ON {$prefijo}ope_incidencias (evidencia_foto_id)
            WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_incidencias
                ADD CONSTRAINT {$prefijo}ope_incidencias_tipo_chk
                    CHECK (tipo IN ('caldo', 'esc', 'bateria', 'mecanica', 'clima', 'otro'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ope_incidencias');
    }
};
