<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Mantenimiento (`man_`) — orden de mantenimiento de un equipo (HU-37,
 * tarea 53, `plan_sprints.md` Sprint 11, §235): "como encargado, quiero abrir
 * órdenes de mantenimiento y cerrarlas consumiendo repuestos, para que el
 * costo quede imputado".
 *
 * `equipo_tipo` + `equipo_id`: apuntan a `ope_drones.id` o `man_vehiculos.id`
 * según `equipo_tipo` — dos tablas distintas no pueden compartir una sola FK,
 * así que `equipo_id` es `unsignedBigInteger` SIN FK real; la existencia del
 * equipo según su tipo se valida en `Aplicacion/`, mismo criterio que la
 * correlación por texto de `ope_recargas.bateria_saliente_id`. Sin
 * `generador`: no existe catálogo de generadores en este alcance (recorte
 * real, no un olvido).
 *
 * `estado` está gobernado por
 * `Aplicacion/MaquinaEstados/MaquinaEstadosOrdenMantenimiento` (invariante 7
 * de CLAUDE.md, a diferencia de `man_vehiculos.estado`/`man_baterias.estado`,
 * que son descriptivos libres): la única transición real es
 * `abierta → cerrada`, con guarda de stock disponible y efecto de dominio
 * (consume `inv_stock`, genera `fin_gastos`).
 *
 * `gasto_id`: FK plana nullable a `fin_gastos.id` (`restrictOnDelete`) — la
 * trazabilidad va al revés de lo habitual: `Mantenimiento` no toca
 * `fin_gastos`, así que en vez de que el gasto sepa de dónde vino, la orden
 * guarda el id del gasto que generó su cierre. NULL mientras la orden está
 * `abierta`; se completa junto con `fecha_cierre` dentro de la misma
 * transacción de `MaquinaEstadosOrdenMantenimiento::cerrar()`.
 *
 * Sin `plan_id`: el modelo completo de la especificación (§4.5) vincula la
 * orden a un plan preventivo, pero `man_planes_mantenimiento` no existe
 * todavía (es la tarea 54, sin prompt escrito cuando se creó esta
 * migración). Se agrega por `ALTER` cuando esa tabla exista, mismo patrón
 * que `capacidad_l` se agregó después a `ope_drones` (tarea 36) y
 * `rendicion_id` después a `fin_gastos` (tarea 48). Por ahora toda orden es
 * autónoma, preventiva o correctiva, sin plan que la dispare.
 *
 * Sin detalle persistido de "qué repuestos se consumieron": esa traza queda
 * en `inv_movimientos.orden_mantenimiento_id` (columna que la tarea 52 dejó
 * sin FK, apuntando a esta tabla que todavía no existía) — se le agrega la
 * FK real acá, ahora que el destino existe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('man_ordenes_mantenimiento', function (Blueprint $table) {
            $table->id();
            $table->string('equipo_tipo', 20);
            $table->unsignedBigInteger('equipo_id');
            $table->string('tipo', 20);
            $table->text('descripcion');
            $table->string('estado', 20);
            $table->dateTime('fecha_apertura');
            $table->dateTime('fecha_cierre')->nullable();
            $table->unsignedBigInteger('gasto_id')->nullable();
            $table->foreign('gasto_id')->references('id')->on('fin_gastos')->restrictOnDelete();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['equipo_tipo', 'equipo_id']);
            $table->index('estado');
        });

        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}man_ordenes_mantenimiento
                ADD CONSTRAINT {$prefijo}man_ordenes_mantenimiento_equipo_tipo_chk
                    CHECK (equipo_tipo IN ('dron', 'vehiculo')),
                ADD CONSTRAINT {$prefijo}man_ordenes_mantenimiento_tipo_chk
                    CHECK (tipo IN ('preventivo', 'correctivo')),
                ADD CONSTRAINT {$prefijo}man_ordenes_mantenimiento_estado_chk
                    CHECK (estado IN ('abierta', 'cerrada'))
            SQL);
        }

        // `inv_movimientos` no tiene ningún índice único parcial (los que
        // trae son planos, `repuesto_id`/`base_id`) — a diferencia del
        // retrofit de `created_by`/`updated_by`, el rebuild de tabla que
        // hace el grammar de SQLite para agregar esta FK no necesita
        // restaurar nada aparte.
        Schema::table('inv_movimientos', function (Blueprint $table) {
            $table->foreign('orden_mantenimiento_id')
                ->references('id')->on('man_ordenes_mantenimiento')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inv_movimientos', function (Blueprint $table) {
            $table->dropForeign(['orden_mantenimiento_id']);
        });

        Schema::dropIfExists('man_ordenes_mantenimiento');
    }
};
