<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `ope_recepciones_caldo` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo OPE.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000058_create_ope_recepciones_caldo_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ope_recepciones_caldo')) {
            return;
        }

        Schema::create('ope_recepciones_caldo', function (Blueprint $table) {
            $table->id();
            $table->string('uuid_cliente', 36);
            $table->foreignId('trabajo_id')->constrained('ope_trabajos')->restrictOnDelete();
            $table->decimal('litros', 10, 2);
            $table->string('entregado_por');
            $table->dateTime('hora');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['trabajo_id'], 'ope_recepciones_caldo_trabajo_id_index');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_recepciones_caldo_uuid_cliente_unico ON {$prefijo}ope_recepciones_caldo USING btree (uuid_cliente) WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_recepciones_caldo
                ADD CONSTRAINT {$prefijo}ope_recepciones_caldo_litros_chk CHECK (litros >= 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ope_recepciones_caldo');
    }
};
