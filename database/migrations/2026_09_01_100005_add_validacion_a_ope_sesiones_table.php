<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Operaciones — validación de sesión (espec §4.3/§5, HU-14, tarea
 * 14): el jefe de campo aprueba una sesión `cerrado` desde el panel.
 * `validado_por` (FK `per_personas`, invariante 4: se compara a nivel
 * persona, no de rol) y `fecha_validacion` son las mismas columnas que la
 * migración de la tarea 09 ya anticipaba y dejaba fuera de alcance
 * ("dependen del flujo de validación (HU-14)").
 *
 * El CHECK de `estado` (tarea 09) se reemplaza por uno que agrega
 * `'validado'` al catálogo — Postgres no admite editar un CHECK existente,
 * hay que soltarlo y volver a crearlo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ope_sesiones', function (Blueprint $table) {
            $table->foreignId('validado_por')->nullable()->after('cierre_uuid_cliente')
                ->constrained('per_personas')->restrictOnDelete();
            $table->dateTime('fecha_validacion')->nullable()->after('validado_por');
        });

        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD/DROP CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_sesiones
                DROP CONSTRAINT {$prefijo}ope_sesiones_estado_chk
            SQL);

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_sesiones
                ADD CONSTRAINT {$prefijo}ope_sesiones_estado_chk
                    CHECK (estado IN ('abierto', 'cerrado', 'validado'))
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_sesiones
                DROP CONSTRAINT {$prefijo}ope_sesiones_estado_chk
            SQL);

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_sesiones
                ADD CONSTRAINT {$prefijo}ope_sesiones_estado_chk
                    CHECK (estado IN ('abierto', 'cerrado'))
            SQL);
        }

        Schema::table('ope_sesiones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('validado_por');
            $table->dropColumn('fecha_validacion');
        });
    }
};
