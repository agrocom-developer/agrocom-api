<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Finanzas (`fin_`, ADR 0011) — devengo por persona y sesión (espec
 * §4.4: "Se calcula por sesión, no por lote... Se genera automáticamente al
 * validar un trabajo, nunca al cerrarlo"; HU-16, tarea 16). Primera tabla del
 * módulo: nace acá, no en una migración de esqueleto separada.
 *
 * `UNIQUE (sesion_id, persona_id)` es la idempotencia REAL de la invariante 3
 * de CLAUDE.md — la que `runs/14.md` ("Qué espera HU-16") deja pendiente como
 * segunda capa: si `SesionValidada` llegara a dispararse dos veces para la
 * misma sesión, el listener no duplica el devengo porque la base lo rechaza
 * (mismo criterio que `ope_sesiones_uuid_cliente_unico`, capturado como
 * `QueryException` en `Finanzas/Aplicacion/GenerarDevengosSesion.php`).
 * Parcial (`WHERE deleted_at IS NULL`) porque `fin_devengos_personal` hereda
 * soft delete de `ModeloDominio` como todo modelo de dominio (ADR 0007).
 *
 * `hectareas`/`tarifa_ha`/`monto` son una copia congelada al momento de
 * validar, no una referencia recalculable desde `per_personas.tarifa_ha`
 * (que puede cambiar después) ni desde `ope_sesiones.hectareas_declaradas`
 * (que no debería, pero la fila de devengo no depende de que no cambie): el
 * devengo es el registro de lo que se pagó, con qué tarifa vigente en ese
 * momento — igual que `com_contratos.monto_total` congela `precio_ha` en vez
 * de recalcular contra el precio de lista actual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_devengos_personal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sesion_id')->constrained('ope_sesiones')->restrictOnDelete();
            $table->foreignId('persona_id')->constrained('per_personas')->restrictOnDelete();
            $table->decimal('hectareas', 10, 2);
            $table->decimal('tarifa_ha', 12, 2);
            $table->decimal('monto', 12, 2);
            $table->date('fecha');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('persona_id');
        });

        $prefijo = DB::getTablePrefix();

        // Idempotencia real (invariante 3): un reintento del mismo devengo
        // choca acá, nunca con un SELECT previo en el caso de uso.
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}fin_devengos_personal_sesion_persona_unico
            ON {$prefijo}fin_devengos_personal (sesion_id, persona_id)
            WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}fin_devengos_personal
                ADD CONSTRAINT {$prefijo}fin_devengos_personal_hectareas_chk
                    CHECK (hectareas >= 0),
                ADD CONSTRAINT {$prefijo}fin_devengos_personal_tarifa_ha_chk
                    CHECK (tarifa_ha >= 0),
                ADD CONSTRAINT {$prefijo}fin_devengos_personal_monto_chk
                    CHECK (monto >= 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_devengos_personal');
    }
};
