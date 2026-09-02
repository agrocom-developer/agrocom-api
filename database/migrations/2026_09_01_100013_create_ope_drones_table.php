<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Operaciones — catálogo mínimo de dron (HU-07, tarea 20). ADR 0011
 * punto 3 reserva `drones` para cuando existan los módulos
 * `Mantenimiento`/`Inventario` (post-v1.0, `plan_sprints.md` "Sprint 10-11")
 * — pero `ope_sesiones.dron_id` (esta misma tarea) necesita una FK real hoy.
 * Se resuelve igual que la tarea 18 resolvió "recibido/consumido/sobrante"
 * sin crear el módulo `Mezclas`: un catálogo sin ciclo de vida propio no
 * amerita módulo aparte, se cuelga del módulo dueño de la agregada que lo
 * referencia (`Operaciones`, prefijo `ope_` ya asignado). Ver runs/20.md.
 *
 * Deliberadamente mínima: solo `id` + `identificador` — nada de
 * `uso_acumulado`, historial de mantenimiento, batería ni ningún dato que le
 * corresponda a `Mantenimiento`/`Inventario` el día que existan. Cuando esos
 * módulos se implementen, esta tabla se reemplaza o se les cede — no se
 * extiende acá con columnas que no le corresponden a `Operaciones`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ope_drones', function (Blueprint $table) {
            $table->id();
            $table->string('identificador', 40);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_drones_identificador_unico
            ON {$prefijo}ope_drones (identificador)
            WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('ope_drones');
    }
};
