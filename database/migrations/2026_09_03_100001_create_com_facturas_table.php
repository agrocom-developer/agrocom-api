<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Comercial (`com_`, ADR 0011) — facturación desde acta conformada
 * (espec Sprint 9 §205; HU-31, tarea 45): "como encargado, quiero emitir la
 * factura de un trabajo desde su acta conformada, para cobrar sobre
 * hectáreas ya firmadas".
 *
 * `acta_id` referencia `ope_actas` (módulo `Operaciones`) SOLO por FK física
 * + entero plano (ADR 0003, regla 3) — sin `belongsTo` en el modelo
 * Eloquent, mismo criterio que `fin_devengos_personal.sesion_id`. `UNIQUE
 * (acta_id)` entre vivas es la guarda real de "no factura dos veces el mismo
 * trabajo": un acta es única por trabajo desde la tarea 24
 * (`ope_actas.trabajo_id` es `UNIQUE`), así que única por acta implica única
 * por trabajo.
 *
 * `hectareas_facturadas`/`precio_ha`/`monto` son una copia congelada al
 * momento de emitir, nunca recalculada después — mismo criterio que
 * `fin_devengos_personal.hectareas`/`tarifa_ha`/`monto` (invariante 6 de
 * CLAUDE.md: todo monto derivado se recalcula desde estos campos propios,
 * nunca desde el estado vivo del contrato o el acta).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('com_facturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained('com_contratos')->restrictOnDelete();
            $table->foreignId('acta_id')->constrained('ope_actas')->restrictOnDelete();
            $table->decimal('hectareas_facturadas', 10, 2);
            $table->decimal('precio_ha', 10, 2);
            $table->decimal('monto', 12, 2);
            $table->date('fecha_emision');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('contrato_id');
        });

        $prefijo = DB::getTablePrefix();

        // Guarda real de "no factura dos veces el mismo trabajo": un
        // reintento sobre la misma acta choca acá, nunca solo con un SELECT
        // previo en el caso de uso.
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}com_facturas_acta_unico
            ON {$prefijo}com_facturas (acta_id)
            WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}com_facturas
                ADD CONSTRAINT {$prefijo}com_facturas_hectareas_facturadas_chk
                    CHECK (hectareas_facturadas >= 0),
                ADD CONSTRAINT {$prefijo}com_facturas_precio_ha_chk
                    CHECK (precio_ha >= 0),
                ADD CONSTRAINT {$prefijo}com_facturas_monto_chk
                    CHECK (monto >= 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('com_facturas');
    }
};
