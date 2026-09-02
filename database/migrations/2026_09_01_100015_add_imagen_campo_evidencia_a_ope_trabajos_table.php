<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Operaciones — imagen del campo obligatoria al cerrar el lote (espec
 * §9: "lote conformado sin imagen del campo" es alerta de "sin evidencia";
 * §9 exige "imagen del campo (capturada por el dron)" como contenido
 * obligatorio del reporte técnico por lote; HU-09, tarea 21).
 *
 * `imagen_campo_evidencia_id` referencia `ope_evidencias.id` con FK real (no
 * el patrón "FK plano sin relación Eloquent" de ADR 0003 regla 3: ese
 * criterio rige cruces de MÓDULO, y `Evidencia`/`Trabajo` viven los dos en
 * `Operaciones`). Nullable porque el trabajo existe desde su apertura, antes
 * de que haya evidencia que referenciar — se completa recién al cerrar
 * (`EscrituraSincronizacionEloquent::cerrarTrabajo()`), mismo tratamiento que
 * `litros_sobrante` (tarea 18): un dato que nadie más puede reconstruir por
 * el cliente, así que se persiste en el propio cierre en vez de quedar solo
 * validado y descartado.
 *
 * Índice único parcial (hallazgo de la revisión crítica de esta tarea): sin
 * él, la misma evidencia (mismo `uuid_cliente`, la misma foto) podría cerrar
 * dos trabajos distintos — contradice el propósito literal de la HU ("que el
 * trabajo quede completo y DEMOSTRABLE"). Mismo patrón que
 * `ope_trabajos_cierre_uuid_cliente_unico`: `cerrarTrabajo()` ya valida esto
 * de forma explícita ANTES de intentar el `save()` (mensaje de rechazo
 * claro), este índice es el guardarraíl real contra la condición de carrera
 * de dos cierres concurrentes de trabajos DISTINTOS con la misma evidencia
 * (el `lockForUpdate()` de `cerrarTrabajo()` solo serializa cierres del
 * MISMO trabajo, no evita que dos trabajos distintos pasen el chequeo
 * `exists()` a la vez).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ope_trabajos', function (Blueprint $table) {
            $table->foreignId('imagen_campo_evidencia_id')->nullable()->after('litros_sobrante')->constrained('ope_evidencias')->nullOnDelete();
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_trabajos_imagen_campo_evidencia_id_unico
            ON {$prefijo}ope_trabajos (imagen_campo_evidencia_id)
            WHERE imagen_campo_evidencia_id IS NOT NULL AND deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::table('ope_trabajos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('imagen_campo_evidencia_id');
        });
    }
};
