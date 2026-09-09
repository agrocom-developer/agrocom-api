<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Personal (`per_`) — equipo de trabajo (tarea 72, HU-49, ADR 0015
 * punto 3): "el piloto y su auxiliar, no dónde están trabajando". Unidad de
 * imputación para gasto, combustible y estadía (tareas 73/74), con vigencia
 * propia — la formación cambia y el gasto histórico tiene que quedar
 * atribuido a quien integraba el equipo EN ESE MOMENTO, nunca a la formación
 * de hoy.
 *
 * `per_equipos_trabajo`, no `per_equipos`: la especificación §4.5 ya reserva
 * `equipos` para la vista unificada de maquinaria (dron/vehículo/generador).
 *
 * Sin `campania_id` (corrección del dueño del 8/9/2026, ADR 0015 punto 3): el
 * equipo es de Agrocom y en la misma semana trabaja para las campañas de
 * varios clientes. Atarlo a una campaña ajena obligaría a duplicar la
 * cuadrilla por cliente.
 *
 * `base_id`: FK real a `per_bases` (mismo módulo, `belongsTo` legítimo en el
 * modelo Eloquent) — a diferencia de `man_vehiculos.base_id`/
 * `man_generadores.base_id`, acá NO es nullable: un equipo de trabajo nace
 * asignado a una base, es lo que ubica a la cuadrilla.
 *
 * `estado` (`activo`/`inactivo`): campo descriptivo libre, NO una máquina de
 * estados de negocio (CLAUDE.md invariante 7 no aplica acá — mismo criterio
 * que `man_vehiculos.estado`/`man_generadores.estado`).
 *
 * `codigo`: índice único PARCIAL sobre `deleted_at IS NULL` (mismo patrón que
 * `man_generadores.identificador`) — un equipo dado de baja lógica no bloquea
 * el re-alta con el mismo código.
 *
 * `desde`/`hasta`: vigencia del equipo en sí (`hasta` nulo = vigente). Es
 * independiente de la vigencia de sus integrantes/recursos, que vive en las
 * otras dos tablas de esta misma tarea.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('per_equipos_trabajo', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20);
            $table->string('nombre', 150)->nullable();
            $table->foreignId('base_id')->constrained('per_bases')->restrictOnDelete();
            $table->string('estado', 20)->default('activo');
            $table->date('desde');
            $table->date('hasta')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('base_id');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}per_equipos_trabajo_codigo_unico
            ON {$prefijo}per_equipos_trabajo (codigo)
            WHERE deleted_at IS NULL
        SQL);

        // Rangos y enums en la base, no solo en la aplicación (ADR 0001).
        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}per_equipos_trabajo
                ADD CONSTRAINT {$prefijo}per_equipos_trabajo_estado_chk
                    CHECK (estado IN ('activo', 'inactivo')),
                ADD CONSTRAINT {$prefijo}per_equipos_trabajo_fechas_chk
                    CHECK (hasta IS NULL OR hasta >= desde)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('per_equipos_trabajo');
    }
};
