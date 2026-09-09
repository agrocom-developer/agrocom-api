<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo `Inventario` (`inv_`) — catálogo de repuestos (HU-36, tarea 52;
 * `plan_sprints.md` Sprint 11): "como encargado, quiero llevar stock de
 * repuestos por base con alerta de mínimo, para reponer antes de quedarme
 * sin". Módulo nuevo, reservado en ADR 0011 (extensión 3/9/2026, punto 15) —
 * no se reparte con `Mantenimiento`: un repuesto en stock no es un equipo
 * con ciclo de desgaste propio.
 *
 * Recortes explícitos frente al modelo más rico de
 * `docs/especificacion/especificacion_funcional_tecnica.md` §4.5/§12 (el CA
 * esencial de `plan_sprints.md` no los pide):
 * - Sin trazabilidad por serie ni marca de "repuesto crítico".
 * - `costo_unitario` NO es un costo promedio ponderado: es el costo de la
 *   ÚLTIMA compra, y se sobrescribe en cada movimiento `tipo=compra` (ver
 *   `RegistrarMovimientoStock`). La tarea 53 (que factura la salida de un
 *   repuesto a un gasto) consume este valor tal cual — está documentado acá
 *   para que no lo reinvente. Nullable: un repuesto puede darse de alta sin
 *   haber tenido todavía ninguna compra.
 *
 * `codigo`: índice único PARCIAL sobre `deleted_at IS NULL`, mismo patrón
 * que `ope_drones.identificador`/`man_baterias.identificador` — un repuesto
 * dado de baja lógica no bloquea el re-alta con el mismo código.
 *
 * `unidad`: string libre sin catálogo cerrado (kg, litros, unidad, ...),
 * mismo criterio que `ope_drones.modelo`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inv_repuestos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 60);
            $table->string('descripcion', 255);
            $table->string('unidad', 30);
            $table->decimal('costo_unitario', 12, 2)->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}inv_repuestos_codigo_unico
            ON {$prefijo}inv_repuestos (codigo)
            WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}inv_repuestos
                ADD CONSTRAINT {$prefijo}inv_repuestos_costo_unitario_chk
                    CHECK (costo_unitario IS NULL OR costo_unitario >= 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_repuestos');
    }
};
