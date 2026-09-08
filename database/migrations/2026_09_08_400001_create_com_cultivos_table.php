<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Comercial — catálogo de cultivos (HU-48, tarea 71, ADR 0015 punto
 * 4): el cultivo NO es columna de `com_lotes` (un lote no "es" de soya, se
 * siembra de soya esta campaña y de maíz la siguiente) — es un catálogo
 * simple, referenciado desde `com_lote_campania` (etapa 2 de esta tarea).
 *
 * `activo` (mismo patrón que `per_personas`): toggle de negocio editable
 * desde el formulario, independiente del soft delete — un cultivo puede
 * desactivarse (dejar de ofrecerse) sin perder su historial en siembras ya
 * cargadas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('com_cultivos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80);
            $table->boolean('activo')->default(true);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $prefijo = DB::getTablePrefix();

        // Nombre único entre cultivos activos (índice parcial, mismo patrón
        // que fin_rubros_nombre_unico): un unique() normal chocaría con el
        // soft delete, porque una fila borrada seguiría ocupando el nombre.
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}com_cultivos_nombre_unico
            ON {$prefijo}com_cultivos (nombre)
            WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('com_cultivos');
    }
};
