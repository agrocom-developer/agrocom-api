<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Finanzas (`fin_`, ADR 0011) — planilla del período, generada desde
 * los devengos y anticipos (espec Sprint 8 §193; HU-30, tarea 44): "como
 * dueño, quiero generar la planilla del período desde los devengos y
 * aprobarla, para pagar con un respaldo que cuadre". Tercera tabla del
 * módulo, después de `fin_devengos_personal` (tarea 40) y `fin_anticipos`
 * (tarea 41), de los que se nutre sin duplicar ninguna cifra de origen.
 *
 * `periodo`: `YYYY-MM`, mes calendario. `UNIQUE` entre vivas — idempotencia
 * por REGLA DE NEGOCIO ("una planilla por mes calendario"), mismo criterio
 * que `ope_actas.trabajo_id` (tarea 24): esto nace en el panel, nunca trae
 * `uuid_cliente` (invariante 1 no aplica).
 *
 * `estado`: dos valores, `borrador`/`aprobada` (ver
 * `Dominio/EstadoPlanilla.php`); transición única, sin vuelta atrás.
 *
 * `total`: suma de los `neto` de sus `fin_planilla_detalles`, calculada y
 * persistida al generar — no una columna generada por Postgres, mismo
 * criterio que `com_contratos.monto_total`.
 *
 * `aprobada_por`/`aprobada_en`: NULL en `borrador`, se fijan juntos al
 * aprobar (`Aplicacion/AprobarPlanilla`). `aprobada_por` referencia
 * `sec_user.id` solo por FK + entero plano (ADR 0003 regla 3) — sin
 * `belongsTo` cross-módulo hacia `Seguridad`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_planillas', function (Blueprint $table) {
            $table->id();
            $table->string('periodo', 7);
            $table->string('estado', 20)->default('borrador');
            $table->decimal('total', 12, 2);
            $table->foreignId('aprobada_por')->nullable()->constrained('sec_user')->restrictOnDelete();
            $table->dateTime('aprobada_en')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $prefijo = DB::getTablePrefix();

        // Idempotencia real (regla de negocio, no invariante 1: esto nace en
        // el panel): un segundo pedido de generación del mismo mes choca acá,
        // nunca con un SELECT previo en el caso de uso.
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}fin_planillas_periodo_unico
            ON {$prefijo}fin_planillas (periodo)
            WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}fin_planillas
                ADD CONSTRAINT {$prefijo}fin_planillas_periodo_chk
                    CHECK (periodo ~ '^\d{4}-(0[1-9]|1[0-2])$'),
                ADD CONSTRAINT {$prefijo}fin_planillas_estado_chk
                    CHECK (estado IN ('borrador', 'aprobada')),
                ADD CONSTRAINT {$prefijo}fin_planillas_total_chk
                    CHECK (total >= 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_planillas');
    }
};
