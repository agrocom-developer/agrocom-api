<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `ope_recargas` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo OPE.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000050_create_ope_recargas_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ope_recargas')) {
            return;
        }

        Schema::create('ope_recargas', function (Blueprint $table) {
            $table->id();
            $table->string('uuid_cliente', 36);
            $table->foreignId('sesion_id')->constrained('ope_sesiones')->restrictOnDelete();
            $table->smallInteger('secuencia');
            $table->decimal('litros_caldo', 10, 2);
            $table->string('bateria_saliente_id');
            $table->decimal('temperatura_bateria_c', 5, 2);
            $table->boolean('alerta_temperatura');
            $table->string('motivo_retraso_caldo', 30)->nullable();
            $table->dateTime('hora_retraso')->nullable();
            $table->decimal('litros_combustible_generador', 10, 2)->nullable();
            $table->dateTime('hora');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['sesion_id', 'secuencia'], 'ope_recargas_sesion_id_secuencia_index');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_recargas_uuid_cliente_unico ON {$prefijo}ope_recargas USING btree (uuid_cliente) WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_recargas
                ADD CONSTRAINT {$prefijo}ope_recargas_combustible_chk CHECK ((litros_combustible_generador IS NULL) OR (litros_combustible_generador >= 0)),
                ADD CONSTRAINT {$prefijo}ope_recargas_litros_caldo_chk CHECK (litros_caldo >= 0),
                ADD CONSTRAINT {$prefijo}ope_recargas_motivo_retraso_chk CHECK ((motivo_retraso_caldo IS NULL) OR motivo_retraso_caldo IN ('filtro_tapado', 'grumos', 'decantacion', 'espuma', 'color_olor_anormal')),
                ADD CONSTRAINT {$prefijo}ope_recargas_secuencia_chk CHECK (secuencia >= 1)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ope_recargas');
    }
};
