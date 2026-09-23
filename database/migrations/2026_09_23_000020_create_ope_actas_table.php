<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `ope_actas` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo OPE.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000020_create_ope_actas_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ope_actas')) {
            return;
        }

        Schema::create('ope_actas', function (Blueprint $table) {
            $table->id();
            $table->string('uuid_cliente', 36);
            $table->foreignId('trabajo_id')->constrained('ope_trabajos')->restrictOnDelete();
            $table->decimal('hectareas_conformadas', 10, 2);
            $table->string('pdf_path')->nullable();
            $table->string('firmante')->nullable();
            $table->dateTime('fecha_firma')->nullable();
            $table->foreignId('evidencia_firma_id')->nullable()->constrained('ope_evidencias')->nullOnDelete();
            $table->string('observaciones', 500)->nullable();
            $table->string('estado', 20)->default('pendiente');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['trabajo_id'], 'ope_actas_trabajo_id_unique');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_actas_evidencia_firma_id_unico ON {$prefijo}ope_actas USING btree (evidencia_firma_id) WHERE (evidencia_firma_id IS NOT NULL) AND (deleted_at IS NULL)
        SQL);

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_actas_uuid_cliente_unico ON {$prefijo}ope_actas USING btree (uuid_cliente) WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_actas
                ADD CONSTRAINT {$prefijo}ope_actas_estado_chk CHECK (estado IN ('pendiente', 'firmada')),
                ADD CONSTRAINT {$prefijo}ope_actas_hectareas_conformadas_chk CHECK (hectareas_conformadas >= 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ope_actas');
    }
};
