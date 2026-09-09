<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Mantenimiento (`man_`, ADR 0011 extensión 3/9/2026, punto 14) —
 * primera tabla del módulo: flota de vehículos con su asignación a base (HU-40,
 * tarea 50, `plan_sprints.md` Sprint 11): "como encargado, quiero administrar
 * los vehículos con su asignación a base".
 *
 * `identificador` (placa o código interno): índice único PARCIAL sobre
 * `deleted_at IS NULL`, mismo patrón que `ope_drones.identificador` — un
 * vehículo dado de baja lógica no bloquea el re-alta con el mismo
 * identificador.
 *
 * `base_id`: FK plana a `per_bases.id` (ADR 0003 regla 3, mismo criterio que
 * `fin_gastos.base_id`) — constraint real en la base, sin `belongsTo`
 * cross-módulo en el modelo Eloquent. Nullable: un vehículo puede darse de
 * alta sin asignación todavía.
 *
 * `estado`: campo descriptivo libre, NO una máquina de estados de negocio
 * (CLAUDE.md invariante 7 no aplica acá — no hay guardas ni transiciones
 * gobernadas por una regla del dominio, a diferencia de
 * `MaquinaEstadosContrato`/`MaquinaEstadosOrden`). Set elegido:
 * `activo` (en operación), `taller` (en mantenimiento/reparación), `de_baja`
 * (fuera de servicio permanente, sin soft-delete — la baja de un vehículo del
 * ABM es otra cosa: `deleted_at`). Default `activo`: todo alta nueva entra
 * operativa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('man_vehiculos', function (Blueprint $table) {
            $table->id();
            $table->string('identificador', 40);
            $table->unsignedBigInteger('base_id')->nullable();
            $table->foreign('base_id')->references('id')->on('per_bases')->restrictOnDelete();
            $table->string('estado', 20)->default('activo');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('base_id');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}man_vehiculos_identificador_unico
            ON {$prefijo}man_vehiculos (identificador)
            WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}man_vehiculos
                ADD CONSTRAINT {$prefijo}man_vehiculos_estado_chk
                    CHECK (estado IN ('activo', 'taller', 'de_baja'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('man_vehiculos');
    }
};
