<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Operaciones — litros consumidos por sesión (espec §7.2, HU-10
 * redefinida por CR-01, tarea 18). A diferencia de `hectareas_declaradas`
 * (OBLIGATORIO en `CierreSesion`, invariante 7 — la espec la fija como
 * condición de la transición `sesión → cerrada`), `litros_consumidos` es
 * NULLABLE y OPCIONAL en el DTO (`CierreSesion::$litrosConsumidos`): la
 * espec no lo declara condición de la transición, y hacerlo obligatorio
 * habría roto en el momento de escribir esta migración todo el lote de
 * tests de cierre ya verdes (tarea 13) que cierran una sesión sin ese campo
 * — un cierre sin dato de caldo (p. ej. `falla_equipo` antes de rociar nada)
 * sigue siendo un cierre válido.
 *
 * Se declara como columna simple igual que `hectareas_declaradas`, no como
 * fila en `ope_recepciones_caldo`: la espec (§7.2) describe UN valor
 * consumido por sesión, no una serie de eventos — mismo criterio que
 * `litros_sobrante` en `ope_trabajos` (ver esa migración).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ope_sesiones', function (Blueprint $table) {
            $table->decimal('litros_consumidos', 10, 2)->nullable()->after('hectareas_declaradas');
        });

        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_sesiones
                ADD CONSTRAINT {$prefijo}ope_sesiones_litros_consumidos_chk
                    CHECK (litros_consumidos IS NULL OR litros_consumidos >= 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::table('ope_sesiones', function (Blueprint $table) {
            $table->dropColumn('litros_consumidos');
        });
    }
};
