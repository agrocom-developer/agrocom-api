<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Mantenimiento (`man_`) — catálogo de generadores (tarea 72, HU-49,
 * ADR 0015 punto 3): no es una HU propia y no debe crecer más allá de esto.
 * Su único consumidor es la asignación de equipamiento a un equipo de
 * trabajo (`per_equipo_recursos.recurso_tipo = 'generador'`); sin esta tabla
 * no hay generador que asignar. Mismo molde exacto que `man_vehiculos`
 * (tarea 50) y `man_baterias` (tarea 51).
 *
 * `identificador`: índice único PARCIAL sobre `deleted_at IS NULL`, mismo
 * patrón que el resto de `man_*` — un generador dado de baja lógica no
 * bloquea el re-alta con el mismo identificador.
 *
 * `modelo`: texto libre nullable, a diferencia de `man_vehiculos` (que no lo
 * tiene) — el ADR no pide validarlo contra ningún catálogo.
 *
 * `base_id`: FK plana a `per_bases.id` (ADR 0003 regla 3), sin `belongsTo`
 * cross-módulo en el modelo Eloquent — mismo criterio que
 * `man_vehiculos.base_id`. Nullable: un generador puede darse de alta sin
 * asignación todavía.
 *
 * `estado`: campo descriptivo libre, NO una máquina de estados de negocio
 * (CLAUDE.md invariante 7 no aplica acá, mismo criterio que
 * `man_vehiculos.estado`). Mismo set: `activo` / `taller` / `de_baja`.
 * Default `activo`.
 *
 * `horas_uso`: nullable, `DECIMAL` (CLAUDE.md invariante 6 — nunca `float`,
 * aunque no sea dinero ni hectáreas, mismo criterio que
 * `ope_drones.capacidad_l`) — no se deriva de nada (a diferencia de
 * `horas_vuelo` de un dron, que sí se acumula desde las sesiones): un
 * generador no vuela, así que el uso se carga a mano.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('man_generadores', function (Blueprint $table) {
            $table->id();
            $table->string('identificador', 40);
            $table->string('modelo', 60)->nullable();
            $table->unsignedBigInteger('base_id')->nullable();
            $table->foreign('base_id')->references('id')->on('per_bases')->restrictOnDelete();
            $table->string('estado', 20)->default('activo');
            $table->decimal('horas_uso', 8, 2)->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('base_id');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}man_generadores_identificador_unico
            ON {$prefijo}man_generadores (identificador)
            WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}man_generadores
                ADD CONSTRAINT {$prefijo}man_generadores_estado_chk
                    CHECK (estado IN ('activo', 'taller', 'de_baja')),
                ADD CONSTRAINT {$prefijo}man_generadores_horas_uso_chk
                    CHECK (horas_uso IS NULL OR horas_uso >= 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('man_generadores');
    }
};
