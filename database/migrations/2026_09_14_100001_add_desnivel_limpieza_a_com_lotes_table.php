<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Comercial — `desnivel`/`limpieza` de `com_lotes` (HU-73, tarea 89).
 * El jefe de campo necesita saberlo ANTES de asignar el equipo (HU-70, ya
 * integrada); hoy solo existe `restricciones`, texto libre para riesgos
 * externos (cables, viviendas, colmenas) que no sirve para filtrar ni
 * planificar de forma sistemática — estos dos son catálogos cerrados
 * aparte, no un reemplazo de `restricciones`.
 *
 * Ambas nullable, sin default: lotes ya cargados no tienen el dato y no hay
 * un valor "razonable" que inventarles. Mismo molde simple que
 * `ope_ordenes_aplicacion.tipo_aplicacion`: string + CHECK IN (...) solo en
 * pgsql + `Rule::in()` en el Request, sin enum PHP dedicado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('com_lotes', function (Blueprint $table) {
            $table->string('desnivel', 20)->nullable()->after('restricciones');
            $table->string('limpieza', 20)->nullable()->after('desnivel');
        });

        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}com_lotes
                ADD CONSTRAINT {$prefijo}com_lotes_desnivel_chk
                    CHECK (desnivel IS NULL OR desnivel IN ('ninguno', 'algunos', 'varios', 'empinado')),
                ADD CONSTRAINT {$prefijo}com_lotes_limpieza_chk
                    CHECK (limpieza IS NULL OR limpieza IN ('limpio', 'algunos_obstaculos', 'muchos_obstaculos'))
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}com_lotes
                DROP CONSTRAINT {$prefijo}com_lotes_desnivel_chk,
                DROP CONSTRAINT {$prefijo}com_lotes_limpieza_chk
            SQL);
        }

        Schema::table('com_lotes', function (Blueprint $table) {
            $table->dropColumn(['desnivel', 'limpieza']);
        });
    }
};
