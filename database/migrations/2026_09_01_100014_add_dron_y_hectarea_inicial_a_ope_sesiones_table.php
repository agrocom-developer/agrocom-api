<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Operaciones — relevo de piloto y cambio de dron (espec §5, HU-07,
 * tarea 20). Dos columnas que la migración de creación de `ope_sesiones`
 * (tarea 09) dejó explícitamente afuera:
 *
 * - `dron_id`: FK a `ope_drones` (catálogo mínimo creado en la migración
 *   anterior). NULLABLE — a diferencia de `piloto_id` (obligatorio desde la
 *   tarea 09), se deja opcional para no romper el lote de sesiones ya
 *   aceptado en `tests/Feature/Api/CierreSincronizacionTest.php` y
 *   `tests/Feature/EscrituraSincronizacionTest.php` (congelado, PR #46) que
 *   abren sesiones sin declarar dron — mismo criterio que `auxiliar_id`, no
 *   el de `piloto_id`. Ver runs/20.md.
 *
 * - `hectarea_inicial_acumulada`: control de doble conteo del acumulado de
 *   DJI (espec §5: "cada sesión registra `hectarea_inicial_acumulada` y las
 *   hectáreas de la sesión son la diferencia contra ese valor"). OPCIONAL,
 *   mismo patrón que `AperturaSesion::$hectareasDeclaradas` — el cálculo de
 *   la diferencia lo hace el dispositivo antes de enviar el cierre
 *   (`CierreSesion::$hectareasDeclaradas`, ya obligatorio ahí); el servidor
 *   solo persiste el valor de partida como dato de trazabilidad, no
 *   recalcula nada a partir de él.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ope_sesiones', function (Blueprint $table) {
            $table->foreignId('dron_id')->nullable()->after('auxiliar_id')->constrained('ope_drones')->restrictOnDelete();
            $table->decimal('hectarea_inicial_acumulada', 10, 2)->nullable()->after('hectareas_declaradas');
        });

        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_sesiones
                ADD CONSTRAINT {$prefijo}ope_sesiones_hectarea_inicial_acumulada_chk
                    CHECK (hectarea_inicial_acumulada IS NULL OR hectarea_inicial_acumulada >= 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::table('ope_sesiones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dron_id');
            $table->dropColumn('hectarea_inicial_acumulada');
        });
    }
};
