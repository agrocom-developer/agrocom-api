<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Distribucion (ADR 0011, extensión 1/9/2026) — versiones del APK
 * (HU-20). `ruta_apk` guarda la clave dentro del disco `r2` (ADR 0009,
 * extensión 1/9/2026: `distribucion/apk/{version}.apk`), nunca una URL: la
 * URL de descarga se firma recién al servir.
 *
 * Solo puede haber una versión `autorizada` a la vez (invariante de negocio,
 * `runs/10-diseno.md`) — respaldado en la base con un índice único parcial,
 * mismo patrón que `com_lotes_codigo_unico`: al filtrar por
 * `estado = 'autorizada'`, todas las filas que matchean comparten el mismo
 * valor de `estado`, así que el índice único fuerza como máximo una.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dis_versiones_apk', function (Blueprint $table) {
            $table->id();
            $table->string('version', 20);
            $table->unsignedInteger('version_code');
            $table->string('ruta_apk');
            $table->string('estado', 20)->default('pendiente');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}dis_versiones_apk_version_unico
            ON {$prefijo}dis_versiones_apk (version)
            WHERE deleted_at IS NULL
        SQL);

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}dis_versiones_apk_version_code_unico
            ON {$prefijo}dis_versiones_apk (version_code)
            WHERE deleted_at IS NULL
        SQL);

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}dis_versiones_apk_autorizada_unica
            ON {$prefijo}dis_versiones_apk (estado)
            WHERE estado = 'autorizada' AND deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}dis_versiones_apk
                ADD CONSTRAINT {$prefijo}dis_versiones_apk_estado_chk
                    CHECK (estado IN ('pendiente', 'autorizada', 'rechazada')),
                ADD CONSTRAINT {$prefijo}dis_versiones_apk_version_code_chk
                    CHECK (version_code > 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dis_versiones_apk');
    }
};
