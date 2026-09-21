<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Operaciones — `ope_estadias_hacienda.campo_id` → `propiedad_id`
 * (ADR 0020, revierte ADR 0018). Única tabla fuera de Comercial acoplada a
 * `Campo` por FK física (ver docblock de `create_ope_estadias_hacienda_table`,
 * ADR 0018 y ahora ADR 0020): la estadía es del terreno donde llegó el
 * equipo, y ese terreno vuelve a ser `com_propiedades` directo.
 *
 * Mismo patrón de 4 pasos que `add_propiedad_id_a_com_lotes_table`: columna
 * nullable + FK → backfill vía `com_campos.propiedad_id` → `NOT NULL` →
 * dropear FK+columna vieja. Ningún índice único de esta tabla involucra
 * `campo_id` (son `uuid_cliente` y `equipo_trabajo_id` con `salida IS NULL`,
 * creados por SQL crudo en `create_ope_estadias_hacienda_table`) — pero en
 * SQLite, `dropForeign()` no tiene equivalente nativo y obliga a Laravel a
 * reconstruir la tabla entera para quitar la FK; ese rebuild solo recrea los
 * índices que el propio Schema Builder conoce (los de `$table->index(...)`),
 * no los creados por `DB::statement(CREATE UNIQUE INDEX ...)`, así que los
 * dos únicos parciales se pierden en SQLite si no se recrean a mano después
 * — mismo motivo por el que `add_propiedad_id_a_com_lotes_table` recrea
 * `com_lotes_codigo_unico` explícitamente. En pgsql sobreviven solos (no hay
 * rebuild), pero se recrean igual acá para no depender de esa diferencia de
 * motor.
 */
return new class extends Migration
{
    public function up(): void
    {
        // (a) columna nueva, nullable por ahora.
        Schema::table('ope_estadias_hacienda', function (Blueprint $table) {
            $table->foreignId('propiedad_id')->nullable()->after('campo_id')
                ->constrained('com_propiedades')->restrictOnDelete();
            $table->index('propiedad_id');
        });

        // (b) backfill vía el campo_id viejo.
        $propiedadPorCampo = DB::table('com_campos')->pluck('propiedad_id', 'id');

        DB::table('ope_estadias_hacienda')->orderBy('id')->chunkById(200, function ($estadias) use ($propiedadPorCampo) {
            foreach ($estadias as $estadia) {
                DB::table('ope_estadias_hacienda')->where('id', $estadia->id)->update([
                    'propiedad_id' => $propiedadPorCampo[$estadia->campo_id] ?? null,
                ]);
            }
        });

        // (c) NOT NULL solo pgsql: SQLite (tests locales rápidos) no soporta
        // ALTER COLUMN ... SET NOT NULL vía Blueprint.
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement("ALTER TABLE {$prefijo}ope_estadias_hacienda ALTER COLUMN propiedad_id SET NOT NULL");
        }

        // (d) dropear FK, índice y columna vieja. El índice btree plano
        // sobre campo_id NO se dropea en cascada junto con la columna en
        // SQLite (a diferencia de pgsql) — hay que dropearlo a mano, mismo
        // criterio que `agrega_equipo_recurso_campania_a_fin_combustibles_table`.
        Schema::table('ope_estadias_hacienda', function (Blueprint $table) {
            $table->dropForeign(['campo_id']);
            $table->dropIndex(['campo_id']);
            $table->dropColumn('campo_id');
        });

        // (e) recrear los dos únicos parciales de SQL crudo (ver docblock):
        // el rebuild de tabla que SQLite hace para el paso (d) los pierde si
        // no se recrean acá. `DROP INDEX IF EXISTS` sin `ON <tabla>` es
        // válido en pgsql y SQLite (mismo criterio ya usado en
        // `add_propiedad_id_a_com_lotes_table`).
        $prefijo = DB::getTablePrefix();

        DB::statement("DROP INDEX IF EXISTS {$prefijo}ope_estadias_hacienda_uuid_cliente_unico");
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_estadias_hacienda_uuid_cliente_unico
            ON {$prefijo}ope_estadias_hacienda (uuid_cliente)
            WHERE deleted_at IS NULL
        SQL);

        DB::statement("DROP INDEX IF EXISTS {$prefijo}ope_estadias_hacienda_equipo_abierta_unico");
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_estadias_hacienda_equipo_abierta_unico
            ON {$prefijo}ope_estadias_hacienda (equipo_trabajo_id)
            WHERE salida IS NULL AND deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        // Rollback de esquema, no de datos: recrea `campo_id` nullable sin
        // repoblarla, mismo criterio que `add_propiedad_id_a_com_lotes_table`.
        Schema::table('ope_estadias_hacienda', function (Blueprint $table) {
            $table->foreignId('campo_id')->nullable()->after('equipo_trabajo_id')
                ->constrained('com_campos')->restrictOnDelete();
            $table->index('campo_id');
        });

        Schema::table('ope_estadias_hacienda', function (Blueprint $table) {
            $table->dropForeign(['propiedad_id']);
            $table->dropColumn('propiedad_id');
        });
    }
};
