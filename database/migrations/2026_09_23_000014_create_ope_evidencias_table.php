<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `ope_evidencias` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo OPE.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000014_create_ope_evidencias_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ope_evidencias')) {
            return;
        }

        Schema::create('ope_evidencias', function (Blueprint $table) {
            $table->id();
            $table->string('uuid_cliente', 36);
            $table->string('tipo', 20);
            $table->string('archivo_url');
            $table->string('hash', 64);
            $table->foreignId('subido_por')->nullable()->constrained('per_personas')->nullOnDelete();
            $table->dateTime('fecha');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tipo'], 'ope_evidencias_tipo_index');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_evidencias_uuid_cliente_unico ON {$prefijo}ope_evidencias USING btree (uuid_cliente) WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_evidencias
                ADD CONSTRAINT {$prefijo}ope_evidencias_tipo_chk CHECK (tipo IN ('captura_rc', 'imagen_campo', 'foto_incidencia', 'comprobante', 'firma_acta', 'foto_control', 'foto_ciclo_bateria_balanceo', 'foto_dron_limpio'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ope_evidencias');
    }
};
