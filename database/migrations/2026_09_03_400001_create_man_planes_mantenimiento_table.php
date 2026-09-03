<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Mantenimiento (`man_`) — planes de mantenimiento preventivo por
 * horas de vuelo de dron (HU-38, tarea 54, `plan_sprints.md` Sprint 11,
 * §236): "como encargado, quiero planes de mantenimiento preventivo por
 * horas de vuelo, para que el sistema me avise antes de la falla".
 *
 * `modelo`: correlaciona por IGUALDAD DE TEXTO contra `ope_drones.modelo`
 * (columna `string(40)` nullable, sin catálogo cerrado — ver docblock de
 * `2026_09_02_100004_add_modelo_capacidad_a_ope_drones_table.php`), NUNCA
 * por FK — mismo criterio exacto que `man_baterias.identificador` contra
 * `ope_recargas.bateria_saliente_id` (tarea 51). Sin índice único: nada
 * impide más de un plan preventivo para el mismo modelo (p. ej. una tarea a
 * las 50 horas y otra a las 100).
 *
 * `tarea`: descripción libre de la tarea preventiva (p. ej. "cambio de
 * hélices"), `text` porque no hay un largo razonable que acotarle.
 *
 * `horas_umbral`: `decimal` (nunca `float`, invariante 6 de CLAUDE.md, con
 * el mismo espíritu aunque no sea plata ni hectáreas) con `CHECK > 0` — un
 * plan sin umbral positivo no dispara alerta nunca, no tiene sentido de
 * negocio.
 *
 * Recortes frente al modelo completo de la especificación (§4.5,
 * `planes_mantenimiento`):
 * - Sin `intervalo_unidad` (horas / km / hectáreas / días): el CA esencial
 *   de esta HU solo pide horas de vuelo de dron. No se generaliza a
 *   km/hectáreas/días todavía — no hay HU que lo necesite (los vehículos no
 *   llevan odómetro en este alcance, tarea 50).
 * - Sin `equipo_tipo` genérico (dron/vehículo/generador): esta tabla es solo
 *   de drones por ahora. Un plan de vehículo o generador es una HU nueva,
 *   no una ampliación silenciosa de esta.
 * - Sin `repuestos_previstos`: no aporta al CA esencial y depende de
 *   `Inventario` con una relación N:M que esta HU no pide.
 *
 * La alerta ("algún dron de este modelo cruzó `horas_umbral`") se calcula al
 * leer (`ListarPlanesMantenimiento`), nunca se persiste acá — mismo criterio
 * que la alerta de `man_baterias` (tarea 51).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('man_planes_mantenimiento', function (Blueprint $table) {
            $table->id();
            $table->string('modelo', 40);
            $table->text('tarea');
            $table->decimal('horas_umbral', 8, 2);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('modelo');
        });

        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}man_planes_mantenimiento
                ADD CONSTRAINT {$prefijo}man_planes_mantenimiento_horas_umbral_chk
                    CHECK (horas_umbral > 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('man_planes_mantenimiento');
    }
};
