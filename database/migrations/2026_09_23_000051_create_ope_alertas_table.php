<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `ope_alertas` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo OPE.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000051_create_ope_alertas_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ope_alertas')) {
            return;
        }

        Schema::create('ope_alertas', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 30);
            $table->foreignId('trabajo_id')->nullable()->constrained('ope_trabajos')->restrictOnDelete();
            $table->foreignId('sesion_id')->nullable()->constrained('ope_sesiones')->restrictOnDelete();
            $table->foreignId('recarga_id')->nullable()->constrained('ope_recargas')->restrictOnDelete();
            $table->foreignId('condiciones_id')->nullable()->constrained('ope_condiciones')->restrictOnDelete();
            $table->foreignId('dron_id')->nullable()->constrained('ope_drones')->restrictOnDelete();
            $table->text('mensaje');
            $table->string('estado', 20)->default('pendiente');
            $table->foreignId('atendida_por')->nullable()->constrained('per_personas')->restrictOnDelete();
            $table->dateTime('atendida_en')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['estado', 'tipo'], 'ope_alertas_estado_tipo_index');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_alertas_bateria_caliente_unico ON {$prefijo}ope_alertas USING btree (recarga_id) WHERE (tipo = 'bateria_caliente') AND (deleted_at IS NULL)
        SQL);

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_alertas_condiciones_forzadas_unico ON {$prefijo}ope_alertas USING btree (condiciones_id) WHERE (tipo = 'condiciones_forzadas') AND (deleted_at IS NULL)
        SQL);

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_alertas_dron_sospechoso_unico ON {$prefijo}ope_alertas USING btree (dron_id) WHERE (tipo = 'dron_sospechoso') AND (deleted_at IS NULL)
        SQL);

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_alertas_suma_excedida_unico ON {$prefijo}ope_alertas USING btree (trabajo_id) WHERE (tipo = 'suma_excedida') AND (deleted_at IS NULL)
        SQL);

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_alertas
                ADD CONSTRAINT {$prefijo}ope_alertas_estado_chk CHECK (estado IN ('pendiente', 'atendida')),
                ADD CONSTRAINT {$prefijo}ope_alertas_tipo_chk CHECK (tipo IN ('bateria_caliente', 'dron_sospechoso', 'condiciones_forzadas', 'suma_excedida'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ope_alertas');
    }
};
