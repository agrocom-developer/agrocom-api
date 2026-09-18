<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Orden de Trabajo" (cabecera de tanda, pedido directo del dueño 18/9/2026,
 * mismo día que `2026_09_18_100001_...`): una Orden de Aplicación se ejecuta
 * en una o más TANDAS — no necesariamente todas sus hectáreas de una vez. Una
 * tanda agrupa los equipos que trabajan juntos ese período con los mismos
 * límites climáticos/parámetros de vuelo/Ph/calda (ej.: semana 1 con 2
 * equipos sobre N lotes; si la aplicación se extiende, otra tanda distinta
 * con 1 solo equipo para los lotes que faltan).
 *
 * Los 8 campos de "Límites climáticos"/"Parámetros de vuelo" que
 * `2026_09_18_100001_...` acababa de mover a `ope_trabajos` (equipo×lote) se
 * mueven UNA VEZ MÁS acá: son compartidos por TODA la tanda, no por equipo
 * individual — `equipo_trabajo_id` se queda en `ope_trabajos` (no sube a esta
 * cabecera, a diferencia de un primer diseño descartado en esta misma tarea:
 * una tanda cubre VARIOS equipos, no uno solo, así que no hay 1-a-1 que
 * expresar con un índice único).
 *
 * `ph_agua`/`ph_calda`: nuevos, solo tienen sentido si la orden es de insumo
 * líquido — validación de FORMA en `AsignarEquipoOrdenRequest` (cruza
 * `ope_ordenes_aplicacion.categoria_insumo_id` → `tipo_insumo`, un CHECK de
 * Postgres no puede leer otra tabla), no acá.
 *
 * Mismo patrón de 3 pasos que `2026_09_14_100019_create_ope_orden_lotes_table`/
 * `2026_09_18_100001_...`: agregar columnas nuevas con sus CHECK → migrar
 * datos → dropear columnas/CHECK viejos del origen (acá, `ope_trabajos`).
 *
 * `turno`/`turno_hora_inicio`/`turno_hora_fin` (nuevos en `ope_trabajos`, no
 * en la cabecera): "qué hace cada equipo dentro de la tanda" (turno
 * mañana/noche/todo el día, con su horario) es propio de cada fila
 * equipo×lote, no compartido — mismo nivel que `lote_id`/`hectareas_declaradas`.
 * Nullable a nivel de columna (los trabajos que nacen por sync puro, sin
 * `equipo_trabajo_id`, no tienen turno); obligatorio a nivel de
 * `AsignarEquiposOrden`/su Request cuando el trabajo nace desde esta pantalla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ope_ordenes_trabajo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_id')->constrained('ope_ordenes_aplicacion')->restrictOnDelete();
            $table->unsignedSmallInteger('nro_aplicacion');

            $table->decimal('humedad_min_pct', 5, 2)->nullable();
            $table->decimal('viento_max_kmh', 5, 2)->nullable();
            $table->decimal('temperatura_max_c', 5, 2)->nullable();
            $table->decimal('humedad_max_pct', 5, 2)->nullable();
            $table->decimal('velocidad_max_kmh', 5, 2)->nullable();
            $table->decimal('altura_vuelo_m', 5, 2)->nullable();
            $table->decimal('velocidad_vuelo_kmh', 5, 2)->nullable();
            $table->decimal('ancho_pasada_m', 5, 2)->nullable();

            $table->decimal('ph_agua', 4, 2)->nullable();
            $table->decimal('ph_calda', 4, 2)->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('orden_id');
        });

        $prefijo = DB::getTablePrefix();

        // Mismos 9 CHECK que ya tenía `ope_trabajos` (8 campos + el cruzado de
        // rango de humedad) más los 2 de Ph (rango físico 0-14) y el de
        // `nro_aplicacion`, igual que el resto de la familia.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_ordenes_trabajo
                ADD CONSTRAINT {$prefijo}ope_ordenes_trabajo_nro_chk
                    CHECK (nro_aplicacion >= 1),
                ADD CONSTRAINT {$prefijo}ope_ordenes_trabajo_humedad_min_chk
                    CHECK (humedad_min_pct IS NULL OR (humedad_min_pct >= 0 AND humedad_min_pct <= 100)),
                ADD CONSTRAINT {$prefijo}ope_ordenes_trabajo_humedad_max_chk
                    CHECK (humedad_max_pct IS NULL OR (humedad_max_pct >= 0 AND humedad_max_pct <= 100)),
                ADD CONSTRAINT {$prefijo}ope_ordenes_trabajo_humedad_rango_chk
                    CHECK (humedad_min_pct IS NULL OR humedad_max_pct IS NULL OR humedad_min_pct <= humedad_max_pct),
                ADD CONSTRAINT {$prefijo}ope_ordenes_trabajo_viento_max_chk
                    CHECK (viento_max_kmh IS NULL OR viento_max_kmh > 0),
                ADD CONSTRAINT {$prefijo}ope_ordenes_trabajo_temperatura_max_chk
                    CHECK (temperatura_max_c IS NULL OR (temperatura_max_c > -10 AND temperatura_max_c < 60)),
                ADD CONSTRAINT {$prefijo}ope_ordenes_trabajo_velocidad_max_chk
                    CHECK (velocidad_max_kmh IS NULL OR velocidad_max_kmh > 0),
                ADD CONSTRAINT {$prefijo}ope_ordenes_trabajo_altura_vuelo_chk
                    CHECK (altura_vuelo_m IS NULL OR altura_vuelo_m > 0),
                ADD CONSTRAINT {$prefijo}ope_ordenes_trabajo_velocidad_vuelo_chk
                    CHECK (velocidad_vuelo_kmh IS NULL OR velocidad_vuelo_kmh > 0),
                ADD CONSTRAINT {$prefijo}ope_ordenes_trabajo_ancho_pasada_chk
                    CHECK (ancho_pasada_m IS NULL OR ancho_pasada_m > 0),
                ADD CONSTRAINT {$prefijo}ope_ordenes_trabajo_ph_agua_chk
                    CHECK (ph_agua IS NULL OR (ph_agua >= 0 AND ph_agua <= 14)),
                ADD CONSTRAINT {$prefijo}ope_ordenes_trabajo_ph_calda_chk
                    CHECK (ph_calda IS NULL OR (ph_calda >= 0 AND ph_calda <= 14))
            SQL);
        }

        Schema::table('ope_trabajos', function (Blueprint $table) {
            $table->foreignId('orden_trabajo_id')->nullable()->after('lote_id')
                ->constrained('ope_ordenes_trabajo')->restrictOnDelete();
            $table->string('turno', 20)->nullable()->after('estado');
            $table->time('turno_hora_inicio')->nullable()->after('turno');
            $table->time('turno_hora_fin')->nullable()->after('turno_hora_inicio');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_trabajos
                ADD CONSTRAINT {$prefijo}ope_trabajos_turno_chk
                    CHECK (turno IS NULL OR turno IN ('manana', 'noche', 'todo_el_dia'))
            SQL);
        }

        // Backfill: cada TRABAJO existente con equipo_trabajo_id ya cargado
        // (nació de `AsignarEquiposOrden` antes de esta tarea) necesita una
        // `OrdenTrabajo` retroactiva. Se agrupa por (orden_id, equipo_trabajo_id)
        // — no porque esa cardinalidad sea la regla a futuro (una tanda ahora
        // puede cubrir VARIOS equipos), sino porque es el único criterio de
        // reconstrucción posible con el dato que ya existe: no hay forma de
        // saber retroactivamente qué trabajos de distintos equipos se
        // ejecutaron "juntos" en la misma tanda. El primer trabajo (por id) de
        // cada grupo es el representante de clima/vuelo — mismo criterio
        // pragmático que ya usó `2026_09_18_100001_...`.
        //
        // Sin `chunkById`: se itera el conjunto de pares (orden_id,
        // equipo_trabajo_id) DISTINTOS — tantos como `OrdenTrabajo` van a
        // resultar, no la tabla `ope_trabajos` completa.
        DB::table('ope_trabajos')
            ->select('orden_id', 'equipo_trabajo_id')
            ->whereNotNull('equipo_trabajo_id')
            ->distinct()
            ->orderBy('orden_id')
            ->orderBy('equipo_trabajo_id')
            ->get()
            ->each(function (object $par): void {
                $representante = DB::table('ope_trabajos')
                    ->where('orden_id', $par->orden_id)
                    ->where('equipo_trabajo_id', $par->equipo_trabajo_id)
                    ->orderBy('id')
                    ->first();

                $ahora = now();

                $ordenTrabajoId = DB::table('ope_ordenes_trabajo')->insertGetId([
                    'orden_id' => $par->orden_id,
                    'nro_aplicacion' => $representante->nro_aplicacion,
                    'humedad_min_pct' => $representante->humedad_min_pct,
                    'viento_max_kmh' => $representante->viento_max_kmh,
                    'temperatura_max_c' => $representante->temperatura_max_c,
                    'humedad_max_pct' => $representante->humedad_max_pct,
                    'velocidad_max_kmh' => $representante->velocidad_max_kmh,
                    'altura_vuelo_m' => $representante->altura_vuelo_m,
                    'velocidad_vuelo_kmh' => $representante->velocidad_vuelo_kmh,
                    'ancho_pasada_m' => $representante->ancho_pasada_m,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);

                // Incluye filas soft-deleted a propósito (sin `whereNull('deleted_at')`):
                // un Trabajo borrado lógicamente igual perteneció a esta tanda;
                // perder el link en el backfill sería peor que conservarlo.
                DB::table('ope_trabajos')
                    ->where('orden_id', $par->orden_id)
                    ->where('equipo_trabajo_id', $par->equipo_trabajo_id)
                    ->update(['orden_trabajo_id' => $ordenTrabajoId]);
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

    /**
     * No intenta reproducir el pasado exacto (mismo criterio pragmático que
     * `2026_09_18_100001_...::down()`): recompone las 8 columnas en
     * `ope_trabajos` desde su `orden_trabajo_id` (acá SÍ hay una fuente única
     * por fila, sin ambigüedad de "primero gana"), y descarta la cabecera
     * entera — `ph_agua`/`ph_calda`/`turno`/horas se pierden sin rastro en el
     * rollback, aceptable: es una baja de la tabla completa.
     */
    public function down(): void
    {
        $prefijo = DB::getTablePrefix();

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

        DB::table('ope_trabajos')
            ->select(['id', 'orden_trabajo_id'])
            ->whereNotNull('orden_trabajo_id')
            ->orderBy('id')
            ->chunkById(200, function ($trabajos): void {
                foreach ($trabajos as $trabajo) {
                    $cabecera = DB::table('ope_ordenes_trabajo')->where('id', $trabajo->orden_trabajo_id)->first();

                    if ($cabecera === null) {
                        continue;
                    }

                    DB::table('ope_trabajos')->where('id', $trabajo->id)->update([
                        'humedad_min_pct' => $cabecera->humedad_min_pct,
                        'viento_max_kmh' => $cabecera->viento_max_kmh,
                        'temperatura_max_c' => $cabecera->temperatura_max_c,
                        'humedad_max_pct' => $cabecera->humedad_max_pct,
                        'velocidad_max_kmh' => $cabecera->velocidad_max_kmh,
                        'altura_vuelo_m' => $cabecera->altura_vuelo_m,
                        'velocidad_vuelo_kmh' => $cabecera->velocidad_vuelo_kmh,
                        'ancho_pasada_m' => $cabecera->ancho_pasada_m,
                    ]);
                }
            }, 'id');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE {$prefijo}ope_trabajos DROP CONSTRAINT {$prefijo}ope_trabajos_turno_chk");
        }

        Schema::table('ope_trabajos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('orden_trabajo_id');
            $table->dropColumn(['turno', 'turno_hora_inicio', 'turno_hora_fin']);
        });

        Schema::dropIfExists('ope_ordenes_trabajo');
    }
};
