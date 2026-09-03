<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Finanzas (`fin_`, ADR 0011) — rendición de campo (espec §4.4; HU-34,
 * tarea 48): "como jefe de campo, quiero rendir los gastos que hice en
 * campo; el encargado los aprueba para reponer el fondo". Cuarta tabla del
 * módulo, después de `fin_gastos` (tarea 47) — de la que se nutre a través de
 * `fin_gastos.rendicion_id` (`ALTER TABLE` de la migración siguiente, mismo
 * criterio que `capacidad_l` en `ope_drones`, tarea 36).
 *
 * `estado`: tres valores, `abierta → presentada → aprobada` (ver
 * `Dominio/EstadoRendicion.php`), sin vuelta atrás; la única clase que los
 * escribe es `Aplicacion/MaquinaEstados/MaquinaEstadosRendicion.php`
 * (invariante 7 de CLAUDE.md).
 *
 * `monto`: NO se carga a mano — lo persiste la máquina de estados como la
 * suma de los `fin_gastos.monto` asociados, en `Brick\Math\BigDecimal`
 * (invariante 6), recalculable siempre desde los gastos de origen. `0.00`
 * mientras la rendición está `abierta` (todavía puede sumar o restar
 * gastos).
 *
 * `jefe_campo_id`/`aprobado_por` referencian `per_personas` solo por FK +
 * entero plano (ADR 0003 regla 3, mismo criterio que `Gasto::base_id`): sin
 * `belongsTo` cross-módulo. `aprobado_por` nullable — se fija recién al
 * aprobar, nunca antes; sostiene la guarda de la invariante 4 de CLAUDE.md
 * (aprobador ≠ jefe de campo, a nivel de PERSONA), que aplica
 * `Dominio/PoliticaAprobacionRendicion` antes de que la máquina de estados
 * toque `estado`.
 *
 * `base_id` referencia `per_bases` con el mismo criterio de FK plana: la base
 * desde la que operó el jefe de campo durante el período rendido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_rendiciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('base_id');
            $table->foreign('base_id')->references('id')->on('per_bases')->restrictOnDelete();
            $table->unsignedBigInteger('jefe_campo_id');
            $table->foreign('jefe_campo_id')->references('id')->on('per_personas')->restrictOnDelete();
            $table->date('fecha');
            $table->text('descripcion')->nullable();
            $table->decimal('monto', 12, 2)->default(0);
            $table->string('estado', 20)->default('abierta');
            $table->unsignedBigInteger('aprobado_por')->nullable();
            $table->foreign('aprobado_por')->references('id')->on('per_personas')->restrictOnDelete();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('base_id');
            $table->index('jefe_campo_id');
            $table->index('estado');
        });

        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}fin_rendiciones
                ADD CONSTRAINT {$prefijo}fin_rendiciones_estado_chk
                    CHECK (estado IN ('abierta', 'presentada', 'aprobada'))
            SQL);

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}fin_rendiciones
                ADD CONSTRAINT {$prefijo}fin_rendiciones_monto_chk
                    CHECK (monto >= 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_rendiciones');
    }
};
