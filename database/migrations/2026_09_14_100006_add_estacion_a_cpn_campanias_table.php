<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `ALTER TABLE cpn_campanias ADD estacion` (HU-77, tarea 93): catálogo
 * cerrado `invierno`/`verano` — hoy la campaña no distinguía si era de
 * invierno o de verano, y el dueño lo pidió el 13/9/2026 junto con el nombre
 * autogenerado (`docs/negocio/observaciones_operaciones_comercial_2026-09-13.md`,
 * fila HU-77).
 *
 * `NOT NULL` sin `DEFAULT`: toda campaña nueva la declara (obligatoria en
 * `CrearCampaniaRequest`/`ActualizarCampaniaRequest`). Nace `nullable()` a
 * nivel de `Schema::table` para poder backfillear antes de reforzar el
 * `NOT NULL` (mismo patrón que `add_campania_id_a_com_contratos_table`). Sin
 * dato real para las filas de demo ya cargadas, se les asigna `'invierno'`
 * explícito acá mismo — nunca un `DEFAULT` silencioso en la columna, que
 * ocultaría el criterio de relleno.
 *
 * `SET NOT NULL`/`ADD CONSTRAINT` solo pgsql (SQLite, motor de los tests, no
 * soporta alterar columnas existentes sin `doctrine/dbal`, que este repo no
 * trae, mismo criterio que `cpn_campanias_estado_chk`) — la obligatoriedad y
 * el catálogo cerrado los sostiene además el Request (`required`,
 * `in:invierno,verano`), que sí se testea.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cpn_campanias', function (Blueprint $table) {
            $table->string('estacion', 10)->nullable()->after('nombre');
        });

        DB::table('cpn_campanias')->whereNull('estacion')->update(['estacion' => 'invierno']);

        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement("ALTER TABLE {$prefijo}cpn_campanias ALTER COLUMN estacion SET NOT NULL");

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}cpn_campanias
                ADD CONSTRAINT {$prefijo}cpn_campanias_estacion_chk
                    CHECK (estacion IN ('invierno', 'verano'))
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement("ALTER TABLE {$prefijo}cpn_campanias DROP CONSTRAINT {$prefijo}cpn_campanias_estacion_chk");
        }

        Schema::table('cpn_campanias', function (Blueprint $table) {
            $table->dropColumn('estacion');
        });
    }
};
