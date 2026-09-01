<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Operaciones — marca de anulación de sesión (invariante 2 de
 * CLAUDE.md, HU-14, tarea 14; diseño completo en runs/14.md).
 *
 * `anulada_en`: la ÚNICA huella que un rechazo deja sobre la fila ORIGINAL.
 * A propósito no es un `estado` (esa columna sigue diciendo `cerrado` para
 * siempre — es lo que de verdad pasó) ni toca ninguna otra columna de
 * negocio (`hectareas_declaradas`, `motivo_cierre`, `piloto_id`, etc.): el
 * rechazo en sí — motivo, autor, a qué sesión anula — vive como fila NUEVA
 * en `ope_sesion_rechazos` (ver esa migración). Nullable: `NULL` = vigente,
 * con fecha = anulada. Las consultas operativas (la cola de validación)
 * filtran por acá; las de auditoría no.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ope_sesiones', function (Blueprint $table) {
            $table->dateTime('anulada_en')->nullable()->after('fecha_validacion');
        });
    }

    public function down(): void
    {
        Schema::table('ope_sesiones', function (Blueprint $table) {
            $table->dropColumn('anulada_en');
        });
    }
};
