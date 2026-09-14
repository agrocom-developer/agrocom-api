<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo `Mezclas` (`mez_`, ADR 0011) — cabecera de la mezcla cargada al
 * caldo (espec §7, HU-78, tarea 94, revierte CR-01). Ver decisión de módulo
 * en el docblock de `create_mez_productos_table` y en `runs/94.md`.
 *
 * Nace en la app de campo con su `uuid_cliente` (invariante 1 de CLAUDE.md),
 * mismo patrón de idempotencia que `ope_recepciones_caldo`: índice único
 * parcial, nunca un `SELECT` previo en el caso de uso.
 *
 * `trabajo_id` referencia `ope_trabajos` — "al crear una aplicación" (nota
 * del dueño, 13/9/2026) es cuando se abre el TRABAJO, no necesariamente
 * cuando existe ya una sesión, mismo criterio que `ope_recepciones_caldo`.
 * Solo FK + entero plano (ADR 0003, regla 3): `Mezclas` no puede importar el
 * Eloquent `Trabajo` de `Operaciones`, así que la resuelve por
 * `Operaciones\Contratos\LecturaTrabajos` (nuevo contrato de lectura,
 * mínimo, agregado por esta tarea) antes del `INSERT`.
 *
 * Puede haber más de un evento `mezcla` por trabajo (igual que
 * `ope_recepciones_caldo` puede tener varios `recepcion_caldo`): no hay
 * restricción de unicidad sobre `trabajo_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mez_mezclas', function (Blueprint $table) {
            $table->id();
            $table->string('uuid_cliente', 36);
            $table->foreignId('trabajo_id')->constrained('ope_trabajos')->restrictOnDelete();
            $table->dateTime('hora');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('trabajo_id');
        });

        $prefijo = DB::getTablePrefix();

        // Idempotencia real (invariante 1): un reintento del mismo lote choca
        // acá, nunca con un SELECT previo en el caso de uso.
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}mez_mezclas_uuid_cliente_unico
            ON {$prefijo}mez_mezclas (uuid_cliente)
            WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('mez_mezclas');
    }
};
