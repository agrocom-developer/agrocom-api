<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Comercial — ventanas horarias permitidas por contrato (insumos §4,
 * RF-60). Son N por contrato (práctica actual: 06:00–10:00 y 16:00–20:00),
 * por eso tabla hija y no columnas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('com_contrato_ventanas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained('com_contratos')->restrictOnDelete();
            $table->time('hora_inicio');
            $table->time('hora_fin');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('contrato_id');
        });

        $prefijo = DB::getTablePrefix();

        // Sin ventanas duplicadas entre las activas (índice parcial, ADR 0001).
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}com_contrato_ventanas_unicas
            ON {$prefijo}com_contrato_ventanas (contrato_id, hora_inicio, hora_fin)
            WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}com_contrato_ventanas
                ADD CONSTRAINT {$prefijo}com_contrato_ventanas_horas_chk
                CHECK (hora_fin > hora_inicio)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('com_contrato_ventanas');
    }
};
