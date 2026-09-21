<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `DROP TABLE com_contrato_alcances`: diseño abandonado (ADR 0018 → ADR
 * 0020) sin modelo Eloquent ni caso de uso que la haya usado nunca — 0 filas
 * en todos los entornos. `com_contrato_lotes` (tarea de detalle de
 * lotes-por-contrato, 16/9/2026) cubre el mismo propósito a nivel `Lote`,
 * más preciso que el nivel `Propiedad` que tenía esta tabla: mantener las
 * dos vivas sería dos formas de expresar "qué cubre el contrato" para la
 * misma cosa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('com_contrato_alcances');
    }

    public function down(): void
    {
        Schema::create('com_contrato_alcances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained('com_contratos')->restrictOnDelete();
            $table->foreignId('propiedad_id')->constrained('com_propiedades')->restrictOnDelete();
            $table->decimal('hectareas', 10, 2);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('contrato_id');
            $table->index('propiedad_id');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}com_contrato_alcances_unico
            ON {$prefijo}com_contrato_alcances (contrato_id, propiedad_id)
            WHERE deleted_at IS NULL
        SQL);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}com_contrato_alcances
                ADD CONSTRAINT {$prefijo}com_contrato_alcances_hectareas_chk
                CHECK (hectareas > 0)
            SQL);
        }
    }
};
