<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Mantenimiento (`man_`) — catálogo de baterías con sus ciclos
 * acumulados y estado (HU-39, tarea 51; `plan_sprints.md` Sprint 11):
 * "como encargado, quiero seguir las baterías con sus ciclos y estado, para
 * retirarlas antes de que fallen en vuelo". Segunda tabla del módulo, mismo
 * molde que `man_vehiculos` (tarea 50).
 *
 * `identificador`: índice único PARCIAL sobre `deleted_at IS NULL`, mismo
 * patrón que `ope_drones.identificador`/`man_vehiculos.identificador` — una
 * batería dada de baja lógica no bloquea el re-alta con el mismo
 * identificador. Es también la clave de correlación de TEXTO (no FK) contra
 * `ope_recargas.bateria_saliente_id` — ver docblock de
 * `App\Dominios\Operaciones\Contratos\LecturaAlertasTemperaturaBateria` para
 * el porqué no se convierte esa columna en FK real.
 *
 * `ciclos_acumulados`: contador que SÍ se espera editar con el uso (a
 * diferencia de un monto ya validado) — entero no negativo, CHECK en la
 * base (ADR 0001). Default 0: toda batería nueva entra sin ciclos.
 *
 * `estado`: campo descriptivo libre, NO una máquina de estados de negocio
 * (CLAUDE.md invariante 7 no aplica acá — mismo criterio que
 * `man_vehiculos.estado`, ver ese docblock). Set elegido: `activa` (en
 * operación) / `retirada` (fuera de servicio permanente, decisión del
 * encargado tras la alerta de ciclos/temperatura — sin soft-delete, que es
 * la baja del ABM). Default `activa`.
 *
 * `base_id`: FK plana a `per_bases.id` (ADR 0003 regla 3), sin `belongsTo`
 * cross-módulo en el modelo Eloquent — mismo criterio que
 * `man_vehiculos.base_id`. Nullable: una batería puede darse de alta sin
 * asignación todavía.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('man_baterias', function (Blueprint $table) {
            $table->id();
            $table->string('identificador', 40);
            $table->unsignedInteger('ciclos_acumulados')->default(0);
            $table->string('estado', 20)->default('activa');
            $table->unsignedBigInteger('base_id')->nullable();
            $table->foreign('base_id')->references('id')->on('per_bases')->restrictOnDelete();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('base_id');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}man_baterias_identificador_unico
            ON {$prefijo}man_baterias (identificador)
            WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}man_baterias
                ADD CONSTRAINT {$prefijo}man_baterias_ciclos_acumulados_chk
                    CHECK (ciclos_acumulados >= 0),
                ADD CONSTRAINT {$prefijo}man_baterias_estado_chk
                    CHECK (estado IN ('activa', 'retirada'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('man_baterias');
    }
};
