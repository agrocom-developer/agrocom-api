<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `fin_gastos` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo FIN.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000032_create_fin_gastos_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fin_gastos')) {
            return;
        }

        Schema::create('fin_gastos', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->foreignId('rubro_id')->constrained('fin_rubros')->restrictOnDelete();
            $table->foreignId('subrubro_id')->nullable()->constrained('fin_subrubros')->restrictOnDelete();
            $table->decimal('cantidad', 12, 2);
            $table->decimal('precio_unitario', 12, 2);
            $table->decimal('monto', 12, 2);
            $table->foreignId('base_id')->nullable()->constrained('per_bases')->restrictOnDelete();
            $table->foreignId('trabajo_id')->nullable()->constrained('ope_trabajos')->restrictOnDelete();
            $table->string('comprobante_url', 500)->nullable();
            $table->string('comprobante_hash', 64)->nullable();
            $table->foreignId('rendicion_id')->nullable()->constrained('fin_rendiciones')->restrictOnDelete();
            $table->foreignId('campania_id')->nullable()->constrained('cpn_campanias')->restrictOnDelete();
            $table->foreignId('equipo_trabajo_id')->nullable()->constrained('per_equipos_trabajo')->restrictOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['base_id'], 'fin_gastos_base_id_index');
            $table->index(['campania_id'], 'fin_gastos_campania_id_index');
            $table->index(['equipo_trabajo_id'], 'fin_gastos_equipo_trabajo_id_index');
            $table->index(['fecha'], 'fin_gastos_fecha_index');
            $table->index(['rendicion_id'], 'fin_gastos_rendicion_id_index');
            $table->index(['rubro_id'], 'fin_gastos_rubro_id_index');
            $table->index(['trabajo_id'], 'fin_gastos_trabajo_id_index');
        });

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}fin_gastos
                ADD CONSTRAINT {$prefijo}fin_gastos_cantidad_chk CHECK (cantidad > 0),
                ADD CONSTRAINT {$prefijo}fin_gastos_monto_chk CHECK (monto > 0),
                ADD CONSTRAINT {$prefijo}fin_gastos_precio_unitario_chk CHECK (precio_unitario > 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_gastos');
    }
};
