<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reforma de Orden de aplicación, pedido directo del dueño 18/9/2026: los 8
 * campos de "Límites climáticos" y "Parámetros de vuelo" que hoy viven en
 * `ope_ordenes_aplicacion` (creados en `2026_08_26_100009_...` como RF-60,
 * "límites por orden") describen en realidad el VUELO que ejecuta cada
 * equipo, no la intención/pedido que es la orden — se mueven a `ope_trabajos`
 * (cada fila ahí es un equipo ejecutando un lote de una orden, ver
 * `2026_09_13_100001_add_equipo_trabajo_a_ope_trabajos_table`).
 *
 * Mismo patrón de 3 pasos que `2026_09_14_100019_create_ope_orden_lotes_table`
 * (que movió `lote_id` de orden a un detalle): agregar columnas nuevas con
 * sus CHECK, migrar datos existentes, recién después dropear columnas y
 * CHECK viejos del origen. La diferencia con aquella migración es la
 * cardinalidad: acá la relación es 1 orden → N trabajos, no 1 a 1.
 *
 * Migración de datos (decisión pragmática, mismo espíritu que el criterio de
 * "no fingir precisión que no existe" de `2026_09_14_100021_...`): para cada
 * orden con algún valor no nulo en estos 8 campos, se replica el mismo valor
 * a TODOS los `Trabajo` no soft-deleted de esa orden. No hay forma de saber
 * retroactivamente si distintos equipos de la misma orden tuvieron
 * condiciones climáticas o parámetros de vuelo distintos — cada equipo hereda,
 * como valor de arranque, las condiciones que tenía la orden.
 *
 * Los CHECK se replican exactos, incluido el cruzado de rango de humedad
 * (`humedad_min_pct <= humedad_max_pct`, cuando ambos están presentes) que ya
 * existía en `ope_ordenes_aplicacion` — no es de los "8 campos" pero viaja
 * con ellos porque depende de dos de esos campos.
 *
 * Sin `Blueprint::change()` (no hay doctrine/dbal): no hace falta acá, las 8
 * columnas son nuevas en `ope_trabajos` y se dropean limpio en el origen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ope_trabajos', function (Blueprint $table) {
            $table->decimal('humedad_min_pct', 5, 2)->nullable()->after('litros_sobrante');
            $table->decimal('viento_max_kmh', 5, 2)->nullable()->after('humedad_min_pct');
            $table->decimal('temperatura_max_c', 5, 2)->nullable()->after('viento_max_kmh');
            $table->decimal('humedad_max_pct', 5, 2)->nullable()->after('temperatura_max_c');
            $table->decimal('velocidad_max_kmh', 5, 2)->nullable()->after('humedad_max_pct');
            $table->decimal('altura_vuelo_m', 5, 2)->nullable()->after('velocidad_max_kmh');
            $table->decimal('velocidad_vuelo_kmh', 5, 2)->nullable()->after('altura_vuelo_m');
            $table->decimal('ancho_pasada_m', 5, 2)->nullable()->after('velocidad_vuelo_kmh');
        });

        $prefijo = DB::getTablePrefix();

        // Rangos en la base, no solo en la aplicación (ADR 0001) — mismos
        // CHECK que tenía `ope_ordenes_aplicacion`.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_trabajos
                ADD CONSTRAINT {$prefijo}ope_trabajos_humedad_min_chk
                    CHECK (humedad_min_pct IS NULL OR (humedad_min_pct >= 0 AND humedad_min_pct <= 100)),
                ADD CONSTRAINT {$prefijo}ope_trabajos_humedad_max_chk
                    CHECK (humedad_max_pct IS NULL OR (humedad_max_pct >= 0 AND humedad_max_pct <= 100)),
                ADD CONSTRAINT {$prefijo}ope_trabajos_humedad_rango_chk
                    CHECK (humedad_min_pct IS NULL OR humedad_max_pct IS NULL OR humedad_min_pct <= humedad_max_pct),
                ADD CONSTRAINT {$prefijo}ope_trabajos_viento_max_chk
                    CHECK (viento_max_kmh IS NULL OR viento_max_kmh > 0),
                ADD CONSTRAINT {$prefijo}ope_trabajos_temperatura_max_chk
                    CHECK (temperatura_max_c IS NULL OR (temperatura_max_c > -10 AND temperatura_max_c < 60)),
                ADD CONSTRAINT {$prefijo}ope_trabajos_velocidad_max_chk
                    CHECK (velocidad_max_kmh IS NULL OR velocidad_max_kmh > 0),
                ADD CONSTRAINT {$prefijo}ope_trabajos_altura_vuelo_chk
                    CHECK (altura_vuelo_m IS NULL OR altura_vuelo_m > 0),
                ADD CONSTRAINT {$prefijo}ope_trabajos_velocidad_vuelo_chk
                    CHECK (velocidad_vuelo_kmh IS NULL OR velocidad_vuelo_kmh > 0),
                ADD CONSTRAINT {$prefijo}ope_trabajos_ancho_pasada_chk
                    CHECK (ancho_pasada_m IS NULL OR ancho_pasada_m > 0)
            SQL);
        }

        // Migración de datos: cada orden con algún valor cargado en estos 8
        // campos lo replica a todos sus trabajos vigentes (ver docblock).
        DB::table('ope_ordenes_aplicacion')
            ->select([
                'id',
                'humedad_min_pct',
                'viento_max_kmh',
                'temperatura_max_c',
                'humedad_max_pct',
                'velocidad_max_kmh',
                'altura_vuelo_m',
                'velocidad_vuelo_kmh',
                'ancho_pasada_m',
            ])
            ->where(function ($query) {
                $query->whereNotNull('humedad_min_pct')
                    ->orWhereNotNull('viento_max_kmh')
                    ->orWhereNotNull('temperatura_max_c')
                    ->orWhereNotNull('humedad_max_pct')
                    ->orWhereNotNull('velocidad_max_kmh')
                    ->orWhereNotNull('altura_vuelo_m')
                    ->orWhereNotNull('velocidad_vuelo_kmh')
                    ->orWhereNotNull('ancho_pasada_m');
            })
            ->orderBy('id')
            ->chunkById(200, function ($ordenes): void {
                foreach ($ordenes as $orden) {
                    DB::table('ope_trabajos')
                        ->where('orden_id', $orden->id)
                        ->whereNull('deleted_at')
                        ->update([
                            'humedad_min_pct' => $orden->humedad_min_pct,
                            'viento_max_kmh' => $orden->viento_max_kmh,
                            'temperatura_max_c' => $orden->temperatura_max_c,
                            'humedad_max_pct' => $orden->humedad_max_pct,
                            'velocidad_max_kmh' => $orden->velocidad_max_kmh,
                            'altura_vuelo_m' => $orden->altura_vuelo_m,
                            'velocidad_vuelo_kmh' => $orden->velocidad_vuelo_kmh,
                            'ancho_pasada_m' => $orden->ancho_pasada_m,
                        ]);
                }
            });

        // Los CHECK viejos se dropean explícitos (no se confía en el cascade
        // del motor al dropear la columna, mismo criterio defensivo que
        // `2026_09_14_100019_...`) — incluye el cruzado de rango de humedad,
        // que depende de dos de las 8 columnas.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_ordenes_aplicacion
                DROP CONSTRAINT {$prefijo}ope_ordenes_aplicacion_humedad_min_chk,
                DROP CONSTRAINT {$prefijo}ope_ordenes_aplicacion_humedad_max_chk,
                DROP CONSTRAINT {$prefijo}ope_ordenes_aplicacion_humedad_rango_chk,
                DROP CONSTRAINT {$prefijo}ope_ordenes_aplicacion_viento_max_chk,
                DROP CONSTRAINT {$prefijo}ope_ordenes_aplicacion_temperatura_max_chk,
                DROP CONSTRAINT {$prefijo}ope_ordenes_aplicacion_velocidad_max_chk,
                DROP CONSTRAINT {$prefijo}ope_ordenes_aplicacion_altura_vuelo_chk,
                DROP CONSTRAINT {$prefijo}ope_ordenes_aplicacion_velocidad_vuelo_chk,
                DROP CONSTRAINT {$prefijo}ope_ordenes_aplicacion_ancho_pasada_chk
            SQL);
        }

        Schema::table('ope_ordenes_aplicacion', function (Blueprint $table) {
            $table->dropColumn([
                'humedad_min_pct',
                'viento_max_kmh',
                'temperatura_max_c',
                'humedad_max_pct',
                'velocidad_max_kmh',
                'altura_vuelo_m',
                'velocidad_vuelo_kmh',
                'ancho_pasada_m',
            ]);
        });
    }

    /**
     * No intenta reproducir el pasado exacto (mismo criterio pragmático que
     * `2026_09_14_100021_...`): con N trabajos por orden, más de uno puede
     * traer valores distintos para estos 8 campos una vez que el flujo normal
     * los edita por equipo — el rollback toma el primero (por `id`) que tenga
     * algún valor cargado como representante de la orden, no un promedio ni
     * una regla de negocio nueva.
     */
    public function down(): void
    {
        $prefijo = DB::getTablePrefix();

        Schema::table('ope_ordenes_aplicacion', function (Blueprint $table) {
            $table->decimal('humedad_min_pct', 5, 2)->nullable()->after('litros_ha');
            $table->decimal('viento_max_kmh', 5, 2)->nullable()->after('humedad_min_pct');
            $table->decimal('temperatura_max_c', 5, 2)->nullable()->after('viento_max_kmh');
            $table->decimal('humedad_max_pct', 5, 2)->nullable()->after('temperatura_max_c');
            $table->decimal('velocidad_max_kmh', 5, 2)->nullable()->after('humedad_max_pct');
            $table->decimal('altura_vuelo_m', 5, 2)->nullable()->after('velocidad_max_kmh');
            $table->decimal('velocidad_vuelo_kmh', 5, 2)->nullable()->after('altura_vuelo_m');
            $table->decimal('ancho_pasada_m', 5, 2)->nullable()->after('velocidad_vuelo_kmh');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_ordenes_aplicacion
                ADD CONSTRAINT {$prefijo}ope_ordenes_aplicacion_humedad_min_chk
                    CHECK (humedad_min_pct IS NULL OR (humedad_min_pct >= 0 AND humedad_min_pct <= 100)),
                ADD CONSTRAINT {$prefijo}ope_ordenes_aplicacion_humedad_max_chk
                    CHECK (humedad_max_pct IS NULL OR (humedad_max_pct >= 0 AND humedad_max_pct <= 100)),
                ADD CONSTRAINT {$prefijo}ope_ordenes_aplicacion_humedad_rango_chk
                    CHECK (humedad_min_pct IS NULL OR humedad_max_pct IS NULL OR humedad_min_pct <= humedad_max_pct),
                ADD CONSTRAINT {$prefijo}ope_ordenes_aplicacion_viento_max_chk
                    CHECK (viento_max_kmh IS NULL OR viento_max_kmh > 0),
                ADD CONSTRAINT {$prefijo}ope_ordenes_aplicacion_temperatura_max_chk
                    CHECK (temperatura_max_c IS NULL OR (temperatura_max_c > -10 AND temperatura_max_c < 60)),
                ADD CONSTRAINT {$prefijo}ope_ordenes_aplicacion_velocidad_max_chk
                    CHECK (velocidad_max_kmh IS NULL OR velocidad_max_kmh > 0),
                ADD CONSTRAINT {$prefijo}ope_ordenes_aplicacion_altura_vuelo_chk
                    CHECK (altura_vuelo_m IS NULL OR altura_vuelo_m > 0),
                ADD CONSTRAINT {$prefijo}ope_ordenes_aplicacion_velocidad_vuelo_chk
                    CHECK (velocidad_vuelo_kmh IS NULL OR velocidad_vuelo_kmh > 0),
                ADD CONSTRAINT {$prefijo}ope_ordenes_aplicacion_ancho_pasada_chk
                    CHECK (ancho_pasada_m IS NULL OR ancho_pasada_m > 0)
            SQL);
        }

        DB::table('ope_trabajos')
            ->select([
                'orden_id',
                'humedad_min_pct',
                'viento_max_kmh',
                'temperatura_max_c',
                'humedad_max_pct',
                'velocidad_max_kmh',
                'altura_vuelo_m',
                'velocidad_vuelo_kmh',
                'ancho_pasada_m',
            ])
            ->whereNull('deleted_at')
            ->where(function ($query) {
                $query->whereNotNull('humedad_min_pct')
                    ->orWhereNotNull('viento_max_kmh')
                    ->orWhereNotNull('temperatura_max_c')
                    ->orWhereNotNull('humedad_max_pct')
                    ->orWhereNotNull('velocidad_max_kmh')
                    ->orWhereNotNull('altura_vuelo_m')
                    ->orWhereNotNull('velocidad_vuelo_kmh')
                    ->orWhereNotNull('ancho_pasada_m');
            })
            ->orderBy('id')
            ->chunkById(200, function ($trabajos): void {
                foreach ($trabajos as $trabajo) {
                    // Primero (por id) que llega gana: la orden ya migrada
                    // en este loop se salta (whereNull de las 8 columnas).
                    DB::table('ope_ordenes_aplicacion')
                        ->where('id', $trabajo->orden_id)
                        ->whereNull('humedad_min_pct')
                        ->whereNull('viento_max_kmh')
                        ->whereNull('temperatura_max_c')
                        ->whereNull('humedad_max_pct')
                        ->whereNull('velocidad_max_kmh')
                        ->whereNull('altura_vuelo_m')
                        ->whereNull('velocidad_vuelo_kmh')
                        ->whereNull('ancho_pasada_m')
                        ->update([
                            'humedad_min_pct' => $trabajo->humedad_min_pct,
                            'viento_max_kmh' => $trabajo->viento_max_kmh,
                            'temperatura_max_c' => $trabajo->temperatura_max_c,
                            'humedad_max_pct' => $trabajo->humedad_max_pct,
                            'velocidad_max_kmh' => $trabajo->velocidad_max_kmh,
                            'altura_vuelo_m' => $trabajo->altura_vuelo_m,
                            'velocidad_vuelo_kmh' => $trabajo->velocidad_vuelo_kmh,
                            'ancho_pasada_m' => $trabajo->ancho_pasada_m,
                        ]);
                }
            });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_trabajos
                DROP CONSTRAINT {$prefijo}ope_trabajos_humedad_min_chk,
                DROP CONSTRAINT {$prefijo}ope_trabajos_humedad_max_chk,
                DROP CONSTRAINT {$prefijo}ope_trabajos_humedad_rango_chk,
                DROP CONSTRAINT {$prefijo}ope_trabajos_viento_max_chk,
                DROP CONSTRAINT {$prefijo}ope_trabajos_temperatura_max_chk,
                DROP CONSTRAINT {$prefijo}ope_trabajos_velocidad_max_chk,
                DROP CONSTRAINT {$prefijo}ope_trabajos_altura_vuelo_chk,
                DROP CONSTRAINT {$prefijo}ope_trabajos_velocidad_vuelo_chk,
                DROP CONSTRAINT {$prefijo}ope_trabajos_ancho_pasada_chk
            SQL);
        }

        Schema::table('ope_trabajos', function (Blueprint $table) {
            $table->dropColumn([
                'humedad_min_pct',
                'viento_max_kmh',
                'temperatura_max_c',
                'humedad_max_pct',
                'velocidad_max_kmh',
                'altura_vuelo_m',
                'velocidad_vuelo_kmh',
                'ancho_pasada_m',
            ]);
        });
    }
};
