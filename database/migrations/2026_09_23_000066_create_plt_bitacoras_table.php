<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `plt_bitacoras` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo PLT.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000066_create_plt_bitacoras_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('plt_bitacoras')) {
            return;
        }

        Schema::create('plt_bitacoras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('sec_user')->restrictOnDelete();
            $table->string('tabla', 63);
            $table->unsignedBigInteger('registro_id');
            $table->string('accion', 20);
            $table->jsonb('antes')->nullable();
            $table->jsonb('despues')->nullable();
            $table->string('zona_horaria', 64)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->index(['tabla', 'registro_id'], 'plt_bitacoras_tabla_registro_id_index');
            $table->index(['user_id'], 'plt_bitacoras_user_id_index');
        });

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}plt_bitacoras
                ADD CONSTRAINT {$prefijo}plt_bitacoras_accion_chk CHECK (accion IN ('creado', 'actualizado', 'eliminado'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plt_bitacoras');
    }
};
