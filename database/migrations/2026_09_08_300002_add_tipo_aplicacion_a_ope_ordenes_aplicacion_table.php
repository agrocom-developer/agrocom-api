<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `ALTER TABLE ope_ordenes_aplicacion ADD tipo_aplicacion` (HU-47, tarea 70):
 * en qué momento del ciclo del cultivo se aplica — el dueño pidió distinguir
 * `siembra` de `cosecha` (7/9/2026); `desarrollo` (aplicaciones desde el
 * desarrollo vegetativo hasta cerca de cosecha — `ventana_al_negocio.md` §67)
 * sale de la propia especificación, no del pedido, y es el default porque
 * cubre el grueso de las 6-8 aplicaciones típicas de un contrato.
 *
 * `NOT NULL DEFAULT 'desarrollo'` (a diferencia de los límites de la orden,
 * que son NULL = hereda): acá no hay nada que heredar, toda orden tiene un
 * momento del ciclo aunque nadie lo haya elegido a propósito.
 *
 * Enum de dominio propio en `Operaciones/Dominio/TipoAplicacion`, mismo
 * criterio que `EstadoOrdenAplicacion`. `CHECK` solo en pgsql (SQLite no
 * soporta `ADD CONSTRAINT` vía Blueprint).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ope_ordenes_aplicacion', function (Blueprint $table) {
            $table->string('tipo_aplicacion', 20)->default('desarrollo')->after('nro_aplicacion');
        });

        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_ordenes_aplicacion
                ADD CONSTRAINT {$prefijo}ope_ordenes_aplicacion_tipo_aplicacion_chk
                CHECK (tipo_aplicacion IN ('siembra', 'desarrollo', 'cosecha'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::table('ope_ordenes_aplicacion', function (Blueprint $table) {
            $table->dropColumn('tipo_aplicacion');
        });
    }
};
