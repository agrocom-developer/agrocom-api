<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `ope_incidencias` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo OPE.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000054_create_ope_incidencias_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ope_incidencias')) {
            return;
        }

        Schema::create('ope_incidencias', function (Blueprint $table) {
            $table->id();
            $table->string('uuid_cliente', 36);
            $table->foreignId('sesion_id')->constrained('ope_sesiones')->restrictOnDelete();
            $table->string('tipo', 20);
            $table->text('descripcion')->nullable();
            $table->dateTime('hora');
            $table->foreignId('evidencia_foto_id')->constrained('ope_evidencias')->restrictOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['sesion_id'], 'ope_incidencias_sesion_id_index');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_incidencias_evidencia_foto_id_unico ON {$prefijo}ope_incidencias USING btree (evidencia_foto_id) WHERE deleted_at IS NULL
        SQL);

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_incidencias_uuid_cliente_unico ON {$prefijo}ope_incidencias USING btree (uuid_cliente) WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_incidencias
                ADD CONSTRAINT {$prefijo}ope_incidencias_tipo_chk CHECK (tipo IN ('caldo', 'esc', 'bateria', 'mecanica', 'clima', 'otro'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ope_incidencias');
    }
};
