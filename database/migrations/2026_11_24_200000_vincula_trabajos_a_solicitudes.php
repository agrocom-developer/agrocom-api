<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El trabajo nace de una solicitud de servicio aceptada (ADR 0037): `ope_trabajos`
 * gana `solicitud_id` y pierde lo que lo ataba a la orden de aplicación, a la
 * orden de trabajo y al lote del modelo de contratos (`orden_id`, `lote_id`,
 * `orden_trabajo_id`, `nro_aplicacion`).
 *
 * Migración destructiva con respaldo dentro de la propia base: antes de quitar las
 * cuatro columnas, sus valores se copian a `respaldo_contratos_y_ordenes.ope_trabajos_vinculos`
 * (solo PostgreSQL, el motor de producción). `down()` las restaura desde ahí.
 * `solicitud_id` es nullable: los trabajos anteriores a las solicitudes no
 * tienen una, y los nuevos la traen siempre (la exige el motor de sync).
 *
 * Retiro definitivo del respaldo: ver `docs/decisiones/0037-solicitud-y-trabajo.md`.
 */
return new class extends Migration
{
    private const ESQUEMA = 'respaldo_contratos_y_ordenes';

    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $p = DB::getTablePrefix();
            $esquema = self::ESQUEMA;

            DB::statement("CREATE SCHEMA IF NOT EXISTS {$esquema}");
            DB::statement("CREATE TABLE IF NOT EXISTS {$esquema}.{$p}ope_trabajos_vinculos AS SELECT id, orden_id, lote_id, orden_trabajo_id, nro_aplicacion FROM {$p}ope_trabajos");
        }

        Schema::table('ope_trabajos', function (Blueprint $table) {
            $table->foreignId('solicitud_id')->nullable()->after('uuid_cliente')->constrained('sol_solicitudes_servicio')->restrictOnDelete();
        });

        Schema::table('ope_trabajos', function (Blueprint $table) {
            $table->dropForeign(['orden_id']);
            $table->dropForeign(['lote_id']);
            $table->dropForeign(['orden_trabajo_id']);
            $table->dropIndex('ope_trabajos_orden_id_index');
            $table->dropIndex('ope_trabajos_lote_id_estado_index');
        });

        if (DB::getDriverName() === 'pgsql') {
            $p = DB::getTablePrefix();

            DB::statement("ALTER TABLE {$p}ope_trabajos DROP CONSTRAINT IF EXISTS {$p}ope_trabajos_nro_chk");
        }

        Schema::table('ope_trabajos', function (Blueprint $table) {
            $table->dropColumn(['orden_id', 'lote_id', 'orden_trabajo_id', 'nro_aplicacion']);
            $table->index(['solicitud_id', 'estado'], 'ope_trabajos_solicitud_id_estado_index');
        });
    }

    public function down(): void
    {
        Schema::table('ope_trabajos', function (Blueprint $table) {
            $table->dropIndex('ope_trabajos_solicitud_id_estado_index');
            $table->unsignedBigInteger('orden_id')->nullable();
            $table->unsignedBigInteger('lote_id')->nullable();
            $table->unsignedBigInteger('orden_trabajo_id')->nullable();
            $table->smallInteger('nro_aplicacion')->nullable();
        });

        if (DB::getDriverName() === 'pgsql') {
            $p = DB::getTablePrefix();
            $esquema = self::ESQUEMA;

            DB::statement("UPDATE {$p}ope_trabajos t SET orden_id = r.orden_id, lote_id = r.lote_id, orden_trabajo_id = r.orden_trabajo_id, nro_aplicacion = r.nro_aplicacion FROM {$esquema}.{$p}ope_trabajos_vinculos r WHERE r.id = t.id");
            DB::statement("DROP TABLE IF EXISTS {$esquema}.{$p}ope_trabajos_vinculos");
        }

        // Las columnas vuelven con sus claves foráneas e índices (pero nullable: los
        // trabajos nacidos desde las solicitudes no tienen orden ni lote), así que
        // volver a aplicar `up()` es posible.
        if (DB::getDriverName() === 'pgsql') {
            Schema::table('ope_trabajos', function (Blueprint $table) {
                $table->foreign('orden_id')->references('id')->on('ope_ordenes_aplicacion')->restrictOnDelete();
                $table->foreign('lote_id')->references('id')->on('com_lotes')->restrictOnDelete();
                $table->foreign('orden_trabajo_id')->references('id')->on('ope_ordenes_trabajo')->restrictOnDelete();
                $table->index(['orden_id'], 'ope_trabajos_orden_id_index');
                $table->index(['lote_id', 'estado'], 'ope_trabajos_lote_id_estado_index');
            });
        }

        Schema::table('ope_trabajos', function (Blueprint $table) {
            $table->dropForeign(['solicitud_id']);
            $table->dropColumn('solicitud_id');
        });
    }
};
