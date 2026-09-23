<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `fin_combustibles` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo FIN.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000025_create_fin_combustibles_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fin_combustibles')) {
            return;
        }

        Schema::create('fin_combustibles', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->foreignId('base_id')->constrained('per_bases')->restrictOnDelete();
            $table->decimal('litros', 10, 2);
            $table->decimal('monto', 12, 2);
            $table->text('descripcion')->nullable();
            $table->foreignId('equipo_trabajo_id')->constrained('per_equipos_trabajo')->restrictOnDelete();
            $table->foreignId('campania_id')->nullable()->constrained('cpn_campanias')->restrictOnDelete();
            $table->string('recurso_tipo', 20);
            $table->unsignedBigInteger('recurso_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['base_id'], 'fin_combustibles_base_id_index');
            $table->index(['campania_id'], 'fin_combustibles_campania_id_index');
            $table->index(['equipo_trabajo_id'], 'fin_combustibles_equipo_trabajo_id_index');
            $table->index(['fecha'], 'fin_combustibles_fecha_index');
            $table->index(['recurso_tipo', 'recurso_id'], 'fin_combustibles_recurso_tipo_recurso_id_index');
        });

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}fin_combustibles
                ADD CONSTRAINT {$prefijo}fin_combustibles_litros_chk CHECK (litros > 0),
                ADD CONSTRAINT {$prefijo}fin_combustibles_monto_chk CHECK (monto > 0),
                ADD CONSTRAINT {$prefijo}fin_combustibles_recurso_tipo_chk CHECK (recurso_tipo IN ('dron', 'vehiculo', 'generador'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_combustibles');
    }
};
