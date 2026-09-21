<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Comercial — `com_lotes.campo_id` → `propiedad_id` (ADR 0020,
 * revierte ADR 0018): `Campo` desaparece como fila con `id` propio, el lote
 * cuelga directo de `com_propiedades`.
 *
 * Patrón de 4 pasos (mover una FK de una tabla a otra sin perder datos):
 * columna nullable + FK → backfill vía el `campo_id` viejo → `NOT NULL` →
 * dropear FK+columna vieja. El backfill es portable entre pgsql (compose,
 * datos reales) y sqlite (tests): mapea `com_campos.id → propiedad_id` con
 * un solo `pluck()` y actualiza `com_lotes` en chunks, sin depender de
 * sintaxis `UPDATE ... FROM` específica de Postgres.
 *
 * El índice único parcial `com_lotes_codigo_unico` se dropea y se recrea
 * **con el mismo nombre** sobre `(propiedad_id, codigo)`:
 * `GuardadoLote::relanzarComoDuplicado()` hace matching de ese string exacto
 * contra el mensaje de la excepción de Postgres para traducirla a
 * `LoteDuplicado` — cambiar el nombre rompería esa traducción en silencio.
 */
return new class extends Migration
{
    public function up(): void
    {
        // (a) columna nueva, nullable por ahora.
        Schema::table('com_lotes', function (Blueprint $table) {
            $table->foreignId('propiedad_id')->nullable()->after('campo_id')
                ->constrained('com_propiedades')->restrictOnDelete();
            $table->index('propiedad_id');
        });

        // (b) backfill vía el campo_id viejo.
        $propiedadPorCampo = DB::table('com_campos')->pluck('propiedad_id', 'id');

        DB::table('com_lotes')->orderBy('id')->chunkById(200, function ($lotes) use ($propiedadPorCampo) {
            foreach ($lotes as $lote) {
                DB::table('com_lotes')->where('id', $lote->id)->update([
                    'propiedad_id' => $propiedadPorCampo[$lote->campo_id] ?? null,
                ]);
            }
        });

        // (c) NOT NULL solo pgsql: SQLite (tests locales rápidos) no soporta
        // ALTER COLUMN ... SET NOT NULL vía Blueprint.
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement("ALTER TABLE {$prefijo}com_lotes ALTER COLUMN propiedad_id SET NOT NULL");
        }

        // (d) dropear el único parcial viejo (campo_id, codigo): tiene que
        // pasar ANTES de dropear la columna — SQLite no permite
        // `ALTER TABLE ... DROP COLUMN` mientras un índice siga
        // referenciándola.
        $prefijo = DB::getTablePrefix();

        DB::statement("DROP INDEX IF EXISTS {$prefijo}com_lotes_codigo_unico");

        // (e) dropear FK, índice y columna vieja. El índice btree plano
        // sobre campo_id NO se dropea en cascada junto con la columna en
        // SQLite (a diferencia de pgsql) — hay que dropearlo a mano, mismo
        // criterio que `agrega_equipo_recurso_campania_a_fin_combustibles_table`.
        Schema::table('com_lotes', function (Blueprint $table) {
            $table->dropForeign(['campo_id']);
            $table->dropIndex(['campo_id']);
            $table->dropColumn('campo_id');
        });

        // (f) recrear el único parcial, mismo nombre, ya sobre
        // (propiedad_id, codigo). DEBE ir después de (e), no antes: en
        // SQLite, `dropColumn()` obliga a Laravel a reconstruir la tabla
        // entera para quitar la columna, y ese rebuild solo recrea los
        // índices que el propio Schema Builder conoce (los de
        // `$table->index(...)`) — un único parcial creado por
        // `DB::statement(CREATE UNIQUE INDEX ...)` antes del rebuild se
        // pierde (queda sin el `WHERE`), mismo motivo por el que
        // `add_propiedad_id_a_ope_estadias_hacienda_table` recrea sus dos
        // únicos parciales después de dropear la columna vieja.
        //
        // `GuardadoLote::relanzarComoDuplicado()` hace matching de este
        // nombre exacto contra el mensaje de la excepción de Postgres para
        // traducirla a `LoteDuplicado` — cambiarlo rompería esa traducción
        // en silencio.
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}com_lotes_codigo_unico
            ON {$prefijo}com_lotes (propiedad_id, codigo)
            WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        // Rollback de esquema, no de datos: recrea `campo_id` nullable sin
        // repoblarla — no hay forma de reconstruir a qué campo pertenecía
        // cada lote una vez que `Campo` deja de existir en el dominio (y,
        // si se está revirtiendo todo el lote de migraciones en orden,
        // `com_campos` ya fue recreada vacía por el down() de
        // `drop_com_campos_table`).
        Schema::table('com_lotes', function (Blueprint $table) {
            $table->foreignId('campo_id')->nullable()->after('id')
                ->constrained('com_campos')->restrictOnDelete();
        });

        $prefijo = DB::getTablePrefix();

        DB::statement("DROP INDEX IF EXISTS {$prefijo}com_lotes_codigo_unico");

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}com_lotes_codigo_unico
            ON {$prefijo}com_lotes (campo_id, codigo)
            WHERE deleted_at IS NULL
        SQL);

        Schema::table('com_lotes', function (Blueprint $table) {
            $table->dropForeign(['propiedad_id']);
            $table->dropColumn('propiedad_id');
        });
    }
};
