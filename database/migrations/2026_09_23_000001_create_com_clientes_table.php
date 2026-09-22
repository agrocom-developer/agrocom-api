<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `com_clientes` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo COM.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000001_create_com_clientes_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('com_clientes')) {
            return;
        }

        Schema::create('com_clientes', function (Blueprint $table) {
            $table->id();
            $table->string('razon_social', 200);
            $table->string('nit', 20)->nullable();
            $table->string('tipo_persona', 10);
            $table->string('ubicacion_oficina')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('nombre_comercial', 200)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}com_clientes_nit_unico ON {$prefijo}com_clientes USING btree (nit) WHERE (nit IS NOT NULL) AND (deleted_at IS NULL)
        SQL);

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}com_clientes
                ADD CONSTRAINT {$prefijo}com_clientes_tipo_persona_chk CHECK (tipo_persona IN ('fisica', 'juridica'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('com_clientes');
    }
};
