<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `fin_rendiciones` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo FIN.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000029_create_fin_rendiciones_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fin_rendiciones')) {
            return;
        }

        Schema::create('fin_rendiciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('base_id')->constrained('per_bases')->restrictOnDelete();
            $table->foreignId('jefe_campo_id')->constrained('per_personas')->restrictOnDelete();
            $table->date('fecha');
            $table->text('descripcion')->nullable();
            $table->decimal('monto', 12, 2)->default(0);
            $table->string('estado', 20)->default('abierta');
            $table->foreignId('aprobado_por')->nullable()->constrained('per_personas')->restrictOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['base_id'], 'fin_rendiciones_base_id_index');
            $table->index(['estado'], 'fin_rendiciones_estado_index');
            $table->index(['jefe_campo_id'], 'fin_rendiciones_jefe_campo_id_index');
        });

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}fin_rendiciones
                ADD CONSTRAINT {$prefijo}fin_rendiciones_estado_chk CHECK (estado IN ('abierta', 'presentada', 'aprobada')),
                ADD CONSTRAINT {$prefijo}fin_rendiciones_monto_chk CHECK (monto >= 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_rendiciones');
    }
};
