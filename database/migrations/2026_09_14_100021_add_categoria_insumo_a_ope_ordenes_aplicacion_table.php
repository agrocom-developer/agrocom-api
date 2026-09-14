<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HU-79 (tarea 110): la orden de aplicación distingue insumo sólido (pide
 * kilos por vuelo) de líquido (litros por hectárea) — vía
 * `categoria_insumo_id` contra el catálogo `ope_categorias_insumo`, que ya
 * trae su propio `tipo_insumo`: la orden no repite ese dato (ver docblock
 * de `create_ope_categorias_insumo_table`).
 *
 * `categoria_insumo_id` nullable: a diferencia de `cantidad_equipos_necesarios`
 * (tarea 107, que sí tenía un valor histórico real — "toda orden era de un
 * solo equipo"), acá no hay ningún dato de origen del que inferir la
 * categoría de las órdenes ya cargadas; forzar un valor sería inventar
 * historia que no existe. El formulario del panel (`CrearOrdenRequest`) sí
 * lo exige `required` para toda orden nueva o editada — el nullable es solo
 * para no mentir sobre el pasado.
 *
 * `litros_ha` deja de ser `NOT NULL`: con insumo sólido la orden no pide
 * litros por hectárea, pide `kilos_por_vuelo` (nueva columna, mismo
 * criterio nullable). Sin doctrine/dbal en el repo, `Blueprint::change()` no
 * está disponible — se resuelve dropeando y recreando la columna (mismo
 * criterio que la migración de horas de generadores,
 * `2026_09_14_100015_...`), con los valores existentes preservados en
 * memoria entre el drop y el alta de nuevo. Dropear `litros_ha` en Postgres
 * se lleva consigo `ope_ordenes_aplicacion_litros_ha_chk` (el CHECK depende
 * solo de esa columna), así que no hace falta un `DROP CONSTRAINT`
 * explícito antes — mismo comportamiento verificado en esa migración
 * anterior.
 *
 * Ambas columnas nuevas (`litros_ha` recreada y `kilos_por_vuelo`) son
 * mutuamente excluyentes según `categoria_insumo_id.tipo_insumo`, pero esa
 * regla cruza `ope_ordenes_aplicacion` con `ope_categorias_insumo` — un
 * `CHECK` de Postgres no puede condicionar sobre una tabla ajena (mismo
 * límite que ya documentó la tarea 107 para "una orden vigente por lote").
 * La guarda vive en `CrearOrdenRequest`/`ActualizarOrdenRequest::withValidator()`,
 * no en el esquema.
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefijo = DB::getTablePrefix();

        // Valores existentes de litros_ha, a preservar tras el drop+alta.
        $litrosHaPorOrden = DB::table('ope_ordenes_aplicacion')->pluck('litros_ha', 'id');

        Schema::table('ope_ordenes_aplicacion', function (Blueprint $table) {
            $table->foreignId('categoria_insumo_id')
                ->nullable()
                ->after('tipo_aplicacion')
                ->constrained('ope_categorias_insumo')
                ->restrictOnDelete();

            $table->decimal('kilos_por_vuelo', 8, 2)->nullable()->after('categoria_insumo_id');
        });

        Schema::table('ope_ordenes_aplicacion', function (Blueprint $table) {
            $table->dropColumn('litros_ha');
        });

        Schema::table('ope_ordenes_aplicacion', function (Blueprint $table) {
            $table->decimal('litros_ha', 8, 2)->nullable()->after('kilos_por_vuelo');
        });

        foreach ($litrosHaPorOrden as $id => $litrosHa) {
            DB::table('ope_ordenes_aplicacion')->where('id', $id)->update(['litros_ha' => $litrosHa]);
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_ordenes_aplicacion
                ADD CONSTRAINT {$prefijo}ope_ordenes_aplicacion_litros_ha_chk
                    CHECK (litros_ha IS NULL OR litros_ha > 0),
                ADD CONSTRAINT {$prefijo}ope_ordenes_aplicacion_kilos_por_vuelo_chk
                    CHECK (kilos_por_vuelo IS NULL OR kilos_por_vuelo > 0)
            SQL);
        }
    }

    /**
     * No restaura el `NOT NULL` original de `litros_ha`: mismo criterio
     * pragmático que la tarea 107 aplicó a `lote_id` en su propio `down()`
     * (lo recrea `nullable()`, no como era antes de esa migración) — un
     * rollback no necesita reproducir un estado que ya no es correcto una
     * vez que existe `kilos_por_vuelo` como alternativa.
     */
    public function down(): void
    {
        $prefijo = DB::getTablePrefix();

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_ordenes_aplicacion
                DROP CONSTRAINT {$prefijo}ope_ordenes_aplicacion_kilos_por_vuelo_chk,
                DROP CONSTRAINT {$prefijo}ope_ordenes_aplicacion_litros_ha_chk
            SQL);
        }

        Schema::table('ope_ordenes_aplicacion', function (Blueprint $table) {
            $table->dropColumn('kilos_por_vuelo');
            $table->dropConstrainedForeignId('categoria_insumo_id');
        });
    }
};
