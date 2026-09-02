<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Operaciones — reporte técnico por lote (espec §9, "Técnico — por
 * lote"; HU-18, tarea 25). Se genera SOLO al firmar el acta de conformidad
 * (`ope_actas.estado` `pendiente → firmada`, tarea 24) — nunca por una
 * acción propia del dispositivo, así que, a diferencia de `ope_actas`, esta
 * tabla NO lleva `uuid_cliente`: no hay invariante 1 que aplicar sobre un
 * registro que el servidor genera solo, sin que ningún dispositivo lo pida.
 * La idempotencia real es de negocio ("un trabajo tiene a lo sumo un
 * reporte"), igual que `ope_actas.trabajo_id`.
 *
 * `hora_inicio`/`hora_fin`: snapshot de `MIN(sesiones.inicio)`/
 * `MAX(sesiones.fin)` de las sesiones vigentes del trabajo AL MOMENTO de
 * generar el reporte — mismo criterio que `ope_actas.hectareas_conformadas`
 * (tarea 24): una corrección posterior de una sesión no debe mover un
 * reporte ya emitido. Se persisten como columnas (no solo dentro del PDF)
 * para que queden consultables/auditables sin tener que abrir el binario —
 * ver runs/25.md.
 *
 * `pdf_path`: ruta en el disco `r2` (mismo disco que `ope_actas`/
 * `ope_evidencias`) del PDF ya renderizado, nullable hasta que termine de
 * generarse dentro de la misma transacción (mismo patrón que
 * `ope_actas.pdf_path`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ope_reportes_tecnicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabajo_id')->unique()->constrained('ope_trabajos')->restrictOnDelete();
            $table->dateTime('hora_inicio')->nullable();
            $table->dateTime('hora_fin')->nullable();
            $table->string('pdf_path')->nullable();
            $table->dateTime('generado_en');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ope_reportes_tecnicos');
    }
};
