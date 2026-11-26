<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Disponibilidad del proveedor y filtros de aviso (ADR 0045, puntos 6 y 8).
 *
 * - `prv_proveedores.disponibilidad` y `prv_pilotos.disponibilidad`: el interruptor
 *   manual de cada cuenta (`disponible`, `ocupado`, `en_trabajo`), con la fecha del
 *   último cambio y quién lo hizo. Todas las filas existentes quedan `disponible`.
 * - `neg_preferencias.filtros`: JSON con los filtros del proveedor que gobiernan solo
 *   los avisos (departamentos excluidos, hectáreas mínimas, tipos de aplicación
 *   excluidos…). Es un mecanismo extensible: agregar un filtro no exige migración.
 *
 * Solo agrega. Las columnas de zona (`zona_base_id`, `radio_operacion_km`, `zonas`,
 * `macrorregiones`) quedan sin uso en el contrato y salen en la reconsolidación final.
 * Cada tabla se toca solo si existe, para que las pruebas con esquema parcial la corran.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const TABLAS_CON_DISPONIBILIDAD = ['prv_proveedores', 'prv_pilotos'];

    public function up(): void
    {
        foreach (self::TABLAS_CON_DISPONIBILIDAD as $tabla) {
            if (! Schema::hasTable($tabla) || Schema::hasColumn($tabla, 'disponibilidad')) {
                continue;
            }

            Schema::table($tabla, function (Blueprint $table) {
                $table->string('disponibilidad', 12)->default('disponible');
                $table->dateTime('disponibilidad_cambiada_en')->nullable();
                $table->unsignedBigInteger('disponibilidad_cambiada_por')->nullable();
            });

            // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran con CHECK).
            if (DB::getDriverName() === 'pgsql') {
                $prefijo = DB::getTablePrefix();
                DB::statement(<<<SQL
                    ALTER TABLE {$prefijo}{$tabla}
                    ADD CONSTRAINT {$prefijo}{$tabla}_disponibilidad_chk CHECK (disponibilidad IN ('disponible', 'ocupado', 'en_trabajo')),
                    ADD CONSTRAINT {$prefijo}{$tabla}_disponibilidad_por_fk FOREIGN KEY (disponibilidad_cambiada_por) REFERENCES {$prefijo}sec_user (id) ON DELETE RESTRICT
                SQL);
            }
        }

        if (Schema::hasTable('neg_preferencias') && ! Schema::hasColumn('neg_preferencias', 'filtros')) {
            Schema::table('neg_preferencias', function (Blueprint $table) {
                $table->json('filtros')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('neg_preferencias') && Schema::hasColumn('neg_preferencias', 'filtros')) {
            Schema::table('neg_preferencias', function (Blueprint $table) {
                $table->dropColumn('filtros');
            });
        }

        foreach (self::TABLAS_CON_DISPONIBILIDAD as $tabla) {
            if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, 'disponibilidad')) {
                continue;
            }

            if (DB::getDriverName() === 'pgsql') {
                $prefijo = DB::getTablePrefix();
                DB::statement("ALTER TABLE {$prefijo}{$tabla} DROP CONSTRAINT IF EXISTS {$prefijo}{$tabla}_disponibilidad_por_fk, DROP CONSTRAINT IF EXISTS {$prefijo}{$tabla}_disponibilidad_chk");
            }

            Schema::table($tabla, function (Blueprint $table) {
                $table->dropColumn(['disponibilidad', 'disponibilidad_cambiada_en', 'disponibilidad_cambiada_por']);
            });
        }
    }
};
