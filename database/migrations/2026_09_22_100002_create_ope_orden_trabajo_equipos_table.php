<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Operaciones — condición de pago de cada equipo dentro de una Orden
 * de Trabajo (pedido del dueño, 22/9/2026; ADR 0023). El pago es del trabajo,
 * no de la persona: al armar la orden se elige para cada equipo una tarifa
 * del catálogo de Finanzas (`fin_tarifas`) y, si con ese equipo se negoció
 * otra cosa para ese trabajo puntual (terreno difícil, voleo…), se guardan
 * acá la modalidad y los montos acordados con el motivo.
 *
 * Los montos se COPIAN de la tarifa al crear la orden (no se releen): cambiar
 * la tarifa después no altera órdenes ya armadas, igual que
 * `com_contratos.monto_total` congela `precio_ha`. `tarifa_id` queda como
 * referencia de dónde salió (FK plana, sin relación Eloquent: es tabla de otro
 * módulo, ADR 0003).
 *
 * Una fila por (orden de trabajo, equipo): los `ope_trabajos` de ese equipo en
 * esa orden (uno por lote) comparten la condición; el devengo la resuelve por
 * `(orden_trabajo_id, equipo_trabajo_id)` del trabajo de la sesión validada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ope_orden_trabajo_equipos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_trabajo_id')->constrained('ope_ordenes_trabajo')->restrictOnDelete();
            $table->foreignId('equipo_trabajo_id')->constrained('per_equipos_trabajo')->restrictOnDelete();
            $table->foreignId('tarifa_id')->nullable()->constrained('fin_tarifas')->restrictOnDelete();
            $table->string('modalidad_pago', 10);
            $table->decimal('monto_piloto', 12, 2);
            $table->decimal('monto_auxiliar', 12, 2);
            $table->boolean('negociado')->default(false);
            $table->string('motivo_negociacion', 255)->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('equipo_trabajo_id');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_orden_trabajo_equipos_unico
            ON {$prefijo}ope_orden_trabajo_equipos (orden_trabajo_id, equipo_trabajo_id)
            WHERE deleted_at IS NULL
        SQL);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_orden_trabajo_equipos
                ADD CONSTRAINT {$prefijo}ope_orden_trabajo_equipos_modalidad_chk
                    CHECK (modalidad_pago IN ('por_dia', 'por_ha')),
                ADD CONSTRAINT {$prefijo}ope_orden_trabajo_equipos_monto_piloto_chk
                    CHECK (monto_piloto >= 0),
                ADD CONSTRAINT {$prefijo}ope_orden_trabajo_equipos_monto_auxiliar_chk
                    CHECK (monto_auxiliar >= 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ope_orden_trabajo_equipos');
    }
};
