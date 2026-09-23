<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `dis_versiones_apk` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo DIS.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000023_create_dis_versiones_apk_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dis_versiones_apk')) {
            return;
        }

        Schema::create('dis_versiones_apk', function (Blueprint $table) {
            $table->id();
            $table->string('version', 20);
            $table->integer('version_code');
            $table->string('url_apk');
            $table->string('estado', 20)->default('pendiente');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}dis_versiones_apk_autorizada_unica ON {$prefijo}dis_versiones_apk USING btree (estado) WHERE (estado = 'autorizada') AND (deleted_at IS NULL)
        SQL);

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}dis_versiones_apk_version_code_unico ON {$prefijo}dis_versiones_apk USING btree (version_code) WHERE deleted_at IS NULL
        SQL);

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}dis_versiones_apk_version_unico ON {$prefijo}dis_versiones_apk USING btree (version) WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}dis_versiones_apk
                ADD CONSTRAINT {$prefijo}dis_versiones_apk_estado_chk CHECK (estado IN ('pendiente', 'autorizada', 'rechazada')),
                ADD CONSTRAINT {$prefijo}dis_versiones_apk_version_code_chk CHECK (version_code > 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dis_versiones_apk');
    }
};
