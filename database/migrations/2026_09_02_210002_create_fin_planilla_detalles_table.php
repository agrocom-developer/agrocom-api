<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Finanzas (`fin_`, ADR 0011) — renglón por persona de una
 * `fin_planillas` (HU-30, tarea 44): `devengado` (suma de
 * `fin_devengos_personal` del período, vía `ListarDevengosPersona`) menos
 * `anticipos` (suma de `fin_anticipos` del mismo período) = `neto`.
 *
 * `planilla_id`: `cascadeOnDelete`, a diferencia del resto del esquema que
 * usa `restrictOnDelete` — el detalle no tiene sentido sin su planilla (no
 * es una entidad independiente referenciada desde otro lado, como sí lo son
 * `ope_sesiones`/`per_personas` frente a `fin_devengos_personal`); dar de
 * baja lógica una planilla debe arrastrar sus renglones, no dejarlos
 * huérfanos.
 *
 * `persona_id`: `restrictOnDelete`, mismo criterio que
 * `fin_devengos_personal`/`fin_anticipos` — una persona con planillas
 * generadas no se puede borrar del catálogo sin antes resolver esa
 * referencia.
 *
 * `devengado`/`anticipos`/`neto`: calculados y persistidos al GENERAR la
 * planilla (`Aplicacion/GenerarPlanilla`), no columnas generadas por
 * Postgres — mismo criterio que `com_contratos.monto_total`: un snapshot
 * congelado, no una vista recalculada contra el estado actual de
 * `fin_devengos_personal`/`fin_anticipos` (que de hecho podría haber
 * cambiado después, p. ej. por un anticipo dado de baja).
 *
 * `pdf_path`: nullable — se completa recién al aprobar la planilla
 * (`Aplicacion/AprobarPlanilla`, que genera el recibo individual dentro de
 * la misma transacción), mismo patrón que `ope_actas.pdf_path`.
 *
 * `UNIQUE (planilla_id, persona_id)`: una persona aparece una sola vez por
 * planilla — no parcial por soft delete, porque el detalle nunca se borra
 * suelto (solo en cascada con su planilla).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_planilla_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planilla_id')->constrained('fin_planillas')->cascadeOnDelete();
            $table->foreignId('persona_id')->constrained('per_personas')->restrictOnDelete();
            $table->decimal('devengado', 12, 2);
            $table->decimal('anticipos', 12, 2);
            $table->decimal('neto', 12, 2);
            $table->string('pdf_path')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['planilla_id', 'persona_id']);
        });

        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}fin_planilla_detalles
                ADD CONSTRAINT {$prefijo}fin_planilla_detalles_devengado_chk
                    CHECK (devengado >= 0),
                ADD CONSTRAINT {$prefijo}fin_planilla_detalles_anticipos_chk
                    CHECK (anticipos >= 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_planilla_detalles');
    }
};
