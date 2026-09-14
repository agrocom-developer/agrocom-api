<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `ALTER TABLE ope_trabajos ADD equipo_trabajo_id` (tarea 85, HU-70): "dónde
 * asignarle el trabajo al piloto" — el jefe de campo reparte las hectáreas de
 * una orden vigente entre equipos desde el panel, y cada reparto nace como un
 * `Trabajo` propio con el equipo que le tocó. FK real + entero plano a
 * `per_equipos_trabajo` (ADR 0003 regla 3, mismo criterio que `orden_id`/
 * `lote_id` de esta misma tabla): sin `belongsTo` cruzado, `Personal` se lee
 * solo por contrato (`Personal\Contratos\LecturaEquipoTrabajo`).
 *
 * Nullable: un `Trabajo` sigue naciendo también por sync, con el `uuid_cliente`
 * que trae el dispositivo (invariante 1 de CLAUDE.md) — ese camino no pasa por
 * el reparto del panel y no tiene equipo que asignarle (`abrirTrabajo()` no
 * cambia, ver `EscrituraSincronizacionEloquent`). Los trabajos ya existentes,
 * abiertos antes de esta tarea, tampoco tienen forma de inferir a qué equipo
 * pertenecían — mismo criterio que `fin_gastos.equipo_trabajo_id` (tarea 73):
 * sin migración de datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ope_trabajos', function (Blueprint $table) {
            $table->foreignId('equipo_trabajo_id')->nullable()->after('lote_id')
                ->constrained('per_equipos_trabajo')->restrictOnDelete();
            $table->index('equipo_trabajo_id');
        });
    }

    public function down(): void
    {
        Schema::table('ope_trabajos', function (Blueprint $table) {
            $table->dropForeign(['equipo_trabajo_id']);
            $table->dropColumn('equipo_trabajo_id');
        });
    }
};
