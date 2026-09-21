<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0022 (18/9/2026): la orden de aplicación es UNA aplicación completa del
 * contrato, correlativa, y de a una por vez.
 *
 * Columnas nuevas de `ope_ordenes_aplicacion`:
 * - Pausa: `motivo_pausa`, `pausada_at`, `reanudada_at` (la última pausa; el
 *   historial completo lo guarda la bitácora de auditoría).
 * - Cierre: `cerrada_at` (`vigente → consumida`, acción manual del encargado).
 * - Cancelación: `cancelada_at`, `causa_cancelacion` (`cliente` |
 *   `fuerza_mayor`) y `motivo_cancelacion` (texto libre). La causa decide si
 *   la aplicación consume su número: la de fuerza mayor no.
 *
 * Solo pgsql (SQLite no soporta `ADD CONSTRAINT`, ver `create_com_contratos_table`):
 * el `CHECK` de `estado` suma `pausada` y `cancelada`; `causa_cancelacion` sale
 * de un catálogo cerrado; y una orden `cancelada` siempre trae causa y motivo.
 *
 * Índices únicos parciales (ambos motores), la garantía de base de las dos
 * reglas nuevas:
 * - `una_abierta_por_contrato`: a lo sumo una orden `emitida`/`vigente`/
 *   `pausada` por contrato.
 * - `nro_por_contrato`: el número de aplicación no se repite dentro de un
 *   contrato, salvo entre canceladas por fuerza mayor (que no consumen su
 *   número y se rehacen con el mismo).
 * Las dos ignoran las filas con baja lógica.
 *
 * Datos previos: hasta hoy se admitían varias órdenes con el MISMO número por
 * contrato (una aplicación repartida en varias órdenes) y varias abiertas a la
 * vez. Esos datos no cumplen las reglas nuevas y no se pueden reparar solos
 * sin inventar historia, así que `up()` se detiene con un mensaje que nombra
 * cuáles son, en vez de crear los índices a medias. En una base vacía o ya
 * consistente no hace nada de eso.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->verificarDatosPrevios();

        Schema::table('ope_ordenes_aplicacion', function (Blueprint $table) {
            $table->text('motivo_pausa')->nullable();
            $table->dateTime('pausada_at')->nullable();
            $table->dateTime('reanudada_at')->nullable();
            $table->dateTime('cerrada_at')->nullable();
            $table->dateTime('cancelada_at')->nullable();
            $table->string('causa_cancelacion', 20)->nullable();
            $table->text('motivo_cancelacion')->nullable();
        });

        $prefijo = DB::getTablePrefix();

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_ordenes_aplicacion
                DROP CONSTRAINT {$prefijo}ope_ordenes_aplicacion_estado_chk
            SQL);

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_ordenes_aplicacion
                ADD CONSTRAINT {$prefijo}ope_ordenes_aplicacion_estado_chk
                    CHECK (estado IN ('emitida', 'vigente', 'pausada', 'consumida', 'cancelada', 'vencida')),
                ADD CONSTRAINT {$prefijo}ope_ordenes_aplicacion_causa_cancelacion_chk
                    CHECK (causa_cancelacion IS NULL OR causa_cancelacion IN ('cliente', 'fuerza_mayor')),
                ADD CONSTRAINT {$prefijo}ope_ordenes_aplicacion_cancelada_con_causa_chk
                    CHECK (estado <> 'cancelada' OR (causa_cancelacion IS NOT NULL AND motivo_cancelacion IS NOT NULL))
            SQL);
        }

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_ordenes_aplicacion_una_abierta_por_contrato
            ON {$prefijo}ope_ordenes_aplicacion (contrato_id)
            WHERE deleted_at IS NULL AND estado IN ('emitida', 'vigente', 'pausada')
        SQL);

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_ordenes_aplicacion_nro_por_contrato
            ON {$prefijo}ope_ordenes_aplicacion (contrato_id, nro_aplicacion)
            WHERE deleted_at IS NULL AND NOT (estado = 'cancelada' AND causa_cancelacion = 'fuerza_mayor')
        SQL);
    }

    /**
     * Vuelve al modelo de cuatro estados: una `pausada` se toma por `vigente` y
     * una `cancelada` por `vencida` (el estado cerrado más cercano que existía
     * entonces). Los motivos y fechas nuevos se pierden con las columnas.
     */
    public function down(): void
    {
        $prefijo = DB::getTablePrefix();

        DB::statement("DROP INDEX IF EXISTS {$prefijo}ope_ordenes_aplicacion_nro_por_contrato");
        DB::statement("DROP INDEX IF EXISTS {$prefijo}ope_ordenes_aplicacion_una_abierta_por_contrato");

        DB::table('ope_ordenes_aplicacion')->where('estado', 'pausada')->update(['estado' => 'vigente']);
        DB::table('ope_ordenes_aplicacion')->where('estado', 'cancelada')->update(['estado' => 'vencida']);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_ordenes_aplicacion
                DROP CONSTRAINT {$prefijo}ope_ordenes_aplicacion_cancelada_con_causa_chk,
                DROP CONSTRAINT {$prefijo}ope_ordenes_aplicacion_causa_cancelacion_chk,
                DROP CONSTRAINT {$prefijo}ope_ordenes_aplicacion_estado_chk
            SQL);

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_ordenes_aplicacion
                ADD CONSTRAINT {$prefijo}ope_ordenes_aplicacion_estado_chk
                    CHECK (estado IN ('emitida', 'vigente', 'consumida', 'vencida'))
            SQL);
        }

        Schema::table('ope_ordenes_aplicacion', function (Blueprint $table) {
            $table->dropColumn([
                'motivo_pausa',
                'pausada_at',
                'reanudada_at',
                'cerrada_at',
                'cancelada_at',
                'causa_cancelacion',
                'motivo_cancelacion',
            ]);
        });
    }

    /**
     * Frena la migración, con un mensaje legible, si hay datos que ya rompen las
     * dos reglas nuevas (ver docblock de la clase).
     */
    private function verificarDatosPrevios(): void
    {
        $variasAbiertas = DB::table('ope_ordenes_aplicacion')
            ->whereNull('deleted_at')
            ->whereIn('estado', ['emitida', 'vigente'])
            ->groupBy('contrato_id')
            ->havingRaw('count(*) > 1')
            ->pluck('contrato_id')
            ->all();

        $numerosRepetidos = DB::table('ope_ordenes_aplicacion')
            ->whereNull('deleted_at')
            ->groupBy('contrato_id', 'nro_aplicacion')
            ->havingRaw('count(*) > 1')
            ->get(['contrato_id', 'nro_aplicacion'])
            ->map(fn (object $fila): string => "contrato {$fila->contrato_id} / aplicación {$fila->nro_aplicacion}")
            ->all();

        if ($variasAbiertas === [] && $numerosRepetidos === []) {
            return;
        }

        throw new RuntimeException(
            'ADR 0022: hay órdenes de aplicación que no cumplen las reglas nuevas (una abierta por contrato y '
            .'número de aplicación único por contrato). Contratos con más de una orden abierta: ['
            .implode(', ', $variasAbiertas).']. Números repetidos: ['.implode('; ', $numerosRepetidos).']. '
            .'Cierra, elimina o renumera esas órdenes y vuelve a migrar.',
        );
    }
};
