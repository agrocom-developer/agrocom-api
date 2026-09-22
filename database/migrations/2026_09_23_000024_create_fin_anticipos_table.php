<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `fin_anticipos` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo FIN.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000024_create_fin_anticipos_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fin_anticipos')) {
            return;
        }

        Schema::create('fin_anticipos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persona_id')->constrained('per_personas')->restrictOnDelete();
            $table->decimal('monto', 12, 2);
            $table->date('fecha');
            $table->string('motivo', 200)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['persona_id'], 'fin_anticipos_persona_id_index');
        });

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}fin_anticipos
                ADD CONSTRAINT {$prefijo}fin_anticipos_monto_chk CHECK (monto > 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_anticipos');
    }
};
