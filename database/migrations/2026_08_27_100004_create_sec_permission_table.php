<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Seguridad — permisos abstractos (ADR 0004, HU-01 diseño
 * `modulos-roles` §5). Código con forma `modulo.entidad.accion`
 * (ej. `seguridad.usuario.crear`), validado en la base con CHECK.
 *
 * Sin `id_module`: diferido a HU-02 hasta que exista `sec_module`
 * (diseño `modulos-roles` §1); se agrega por ALTER TABLE en ese momento.
 *
 * `code` es UNIQUE plano (catálogo de sistema, mismo criterio que `sec_role.name`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sec_permission', function (Blueprint $table) {
            $table->id();
            $table->string('code', 150)->unique();
            $table->string('description', 200);
            $table->boolean('state')->default(true);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $prefijo = DB::getTablePrefix();

        // Rangos y enums en la base, no solo en la aplicación (ADR 0001).
        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}sec_permission
                ADD CONSTRAINT {$prefijo}sec_permission_code_chk
                    CHECK (code ~ '^[a-z0-9_]+\.[a-z0-9_]+\.[a-z0-9_]+$')
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sec_permission');
    }
};
