<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `fin_planillas` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo FIN.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000034_create_fin_planillas_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fin_planillas')) {
            return;
        }

        Schema::create('fin_planillas', function (Blueprint $table) {
            $table->id();
            $table->string('periodo', 7);
            $table->string('estado', 20)->default('borrador');
            $table->decimal('total', 12, 2);
            $table->foreignId('aprobada_por')->nullable()->constrained('sec_user')->restrictOnDelete();
            $table->dateTime('aprobada_en')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}fin_planillas_periodo_unico ON {$prefijo}fin_planillas USING btree (periodo) WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}fin_planillas
                ADD CONSTRAINT {$prefijo}fin_planillas_estado_chk CHECK (estado IN ('borrador', 'aprobada')),
                ADD CONSTRAINT {$prefijo}fin_planillas_periodo_chk CHECK (periodo ~ '^\d{4}-(0[1-9]|1[0-2])$'),
                ADD CONSTRAINT {$prefijo}fin_planillas_total_chk CHECK (total >= 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_planillas');
    }
};
