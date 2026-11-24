<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Pasa los datos del modelo de asignación al de negociación (`negociacion-contrato.md`
 * §11) sin borrar nada: cada tabla o columna que sale de `public` va a un esquema
 * `respaldo_*` y `down()` la restaura.
 *
 * - Estados de la solicitud y de su historial: se copian a `respaldo_estados` y se
 *   reescriben al vocabulario nuevo.
 * - `tarifa_hectarea` pasa a `precio_ha_propuesto` (y a `precio_ha_acordado` si ya
 *   había proveedor); las ofertas aceptadas generan su `neg_ofertas` con número 1.
 * - `comision_*` se copia a `respaldo_comision` y se quita de la solicitud.
 * - `asg_*` y `flo_disponibilidad` se mueven a `respaldo_asignacion` y
 *   `respaldo_disponibilidad`.
 *
 * En SQLite (solo las pruebas) los esquemas no existen: las tablas de respaldo
 * se llaman `<esquema>__<tabla>`.
 */
return new class extends Migration
{
    private const ESTADOS_NUEVOS = ['borrador', 'abierta', 'con_ofertas', 'adjudicada', 'en_ejecucion', 'finalizada', 'calificada', 'cancelada', 'expirada', 'disputada'];

    private const ESTADOS_ANTERIORES = ['borrador', 'publicada', 'en_asignacion', 'sin_proveedor', 'asignada', 'en_camino', 'en_ejecucion', 'suspendida_por_clima', 'ejecutada', 'en_disputa', 'completada', 'cancelada'];

    /** @var array<string, string> viejo => nuevo */
    private const MAPA = [
        'publicada' => 'abierta',
        'en_asignacion' => 'abierta',
        'sin_proveedor' => 'expirada',
        'asignada' => 'adjudicada',
        'en_camino' => 'adjudicada',
        'suspendida_por_clima' => 'en_ejecucion',
        'ejecutada' => 'finalizada',
        'completada' => 'finalizada',
        'en_disputa' => 'disputada',
    ];

    private const TABLAS_ASIGNACION = ['asg_rondas', 'asg_ofertas', 'asg_cancelaciones_proveedor', 'asg_sanciones'];

    public function up(): void
    {
        $this->crearEsquemas();
        $this->respaldarEstados();
        $this->reescribirEstados();
        $this->trasladarPrecios();
        $this->generarOfertasAceptadas();
        $this->respaldarYQuitarComision();

        foreach (self::TABLAS_ASIGNACION as $tabla) {
            $this->mover($tabla, 'respaldo_asignacion');
        }
        $this->mover('flo_disponibilidad', 'respaldo_disponibilidad');
    }

    public function down(): void
    {
        $this->mover('flo_disponibilidad', 'public', 'respaldo_disponibilidad');
        foreach (array_reverse(self::TABLAS_ASIGNACION) as $tabla) {
            $this->mover($tabla, 'public', 'respaldo_asignacion');
        }

        $this->restaurarComision();
        $this->restaurarEstados();
    }

    private function pg(): bool
    {
        return DB::getDriverName() === 'pgsql';
    }

    private function nombre(string $esquema, string $tabla): string
    {
        return $this->pg() ? "{$esquema}.{$tabla}" : "{$esquema}__{$tabla}";
    }

    private function crearEsquemas(): void
    {
        if (! $this->pg()) {
            return;
        }

        foreach (['respaldo_estados', 'respaldo_comision', 'respaldo_asignacion', 'respaldo_disponibilidad'] as $esquema) {
            DB::statement("CREATE SCHEMA IF NOT EXISTS {$esquema}");
        }
    }

    private function mover(string $tabla, string $destino, ?string $origen = null): void
    {
        $prefijo = DB::getTablePrefix();

        if ($this->pg()) {
            $desde = $origen === null ? 'public' : $origen;
            if ($desde === $destino) {
                return;
            }
            DB::statement("ALTER TABLE {$desde}.{$prefijo}{$tabla} SET SCHEMA {$destino}");

            return;
        }

        if ($origen === null) {
            Schema::rename($tabla, "{$destino}__{$tabla}");
        } else {
            Schema::rename("{$origen}__{$tabla}", $tabla);
        }
    }

    private function respaldarEstados(): void
    {
        $solicitudes = $this->nombre('respaldo_estados', 'sol_solicitudes_servicio');
        $historial = $this->nombre('respaldo_estados', 'sol_solicitud_historial');
        $prefijo = DB::getTablePrefix();

        DB::statement("CREATE TABLE {$solicitudes} AS SELECT id, estado FROM {$prefijo}sol_solicitudes_servicio");
        DB::statement("CREATE TABLE {$historial} AS SELECT id, estado_anterior, estado_nuevo FROM {$prefijo}sol_solicitud_historial");
    }

    private function reescribirEstados(): void
    {
        $prefijo = DB::getTablePrefix();

        if ($this->pg()) {
            DB::statement("ALTER TABLE {$prefijo}sol_solicitudes_servicio DROP CONSTRAINT {$prefijo}sol_solicitudes_servicio_estado_chk");
        }

        foreach (self::MAPA as $viejo => $nuevo) {
            DB::table('sol_solicitudes_servicio')->where('estado', $viejo)->update(['estado' => $nuevo]);
            DB::table('sol_solicitud_historial')->where('estado_nuevo', $viejo)->update(['estado_nuevo' => $nuevo]);
            DB::table('sol_solicitud_historial')->where('estado_anterior', $viejo)->update(['estado_anterior' => $nuevo]);
        }

        // Una `completada` que el cliente ya calificó pasa a `calificada`.
        DB::table('sol_solicitudes_servicio')
            ->where('estado', 'finalizada')
            ->whereIn('id', DB::table('cal_calificaciones')->where('autor_rol', 'cliente')->select('solicitud_id'))
            ->whereIn('id', DB::table($this->nombre('respaldo_estados', 'sol_solicitudes_servicio'))->where('estado', 'completada')->select('id'))
            ->update(['estado' => 'calificada']);

        if ($this->pg()) {
            $lista = $this->lista(self::ESTADOS_NUEVOS);
            DB::statement("ALTER TABLE {$prefijo}sol_solicitudes_servicio ADD CONSTRAINT {$prefijo}sol_solicitudes_servicio_estado_chk CHECK (estado IN ({$lista}))");
            DB::statement("ALTER TABLE {$prefijo}sol_solicitudes_servicio DROP CONSTRAINT IF EXISTS {$prefijo}sol_solicitudes_servicio_cancelada_con_motivo_chk");
            DB::statement("ALTER TABLE {$prefijo}sol_solicitudes_servicio ADD CONSTRAINT {$prefijo}sol_solicitudes_servicio_cancelada_con_motivo_chk CHECK (estado <> 'cancelada' OR (motivo_cancelacion IS NOT NULL AND cancelada_por IS NOT NULL))");
        }
    }

    private function trasladarPrecios(): void
    {
        DB::table('sol_solicitudes_servicio')->whereNotNull('tarifa_hectarea')->update(['precio_ha_propuesto' => DB::raw('tarifa_hectarea')]);

        DB::table('sol_solicitudes_servicio')
            ->whereNotNull('tarifa_hectarea')
            ->whereNotNull('proveedor_id')
            ->update(['precio_ha_acordado' => DB::raw('tarifa_hectarea'), 'adjudicada_en' => DB::raw('updated_at')]);

        DB::table('sol_solicitudes_servicio')
            ->whereIn('estado', ['finalizada', 'calificada', 'disputada'])
            ->update([
                'hectareas_ejecutadas' => DB::raw('hectareas_solicitadas'),
                'porcentaje_avance' => 100,
                'finalizada_en' => DB::raw('updated_at'),
            ]);

        DB::table('sol_solicitudes_servicio')
            ->where('estado', 'abierta')
            ->whereNull('vence_en')
            ->update(['vence_en' => DB::raw('ventana_hasta')]);
    }

    /** Las ofertas aceptadas del modelo anterior pasan a ser la oferta número 1 de la negociación. */
    private function generarOfertasAceptadas(): void
    {
        $origen = 'asg_ofertas';

        $aceptadas = DB::table($origen)
            ->join('sol_solicitudes_servicio as s', 's.id', '=', "{$origen}.solicitud_id")
            ->where("{$origen}.estado", 'aceptada')
            ->whereNull("{$origen}.deleted_at")
            ->whereNotNull('s.tarifa_hectarea')
            ->select("{$origen}.id as asg_id", "{$origen}.solicitud_id", "{$origen}.proveedor_id", "{$origen}.equipo_id", "{$origen}.respondida_en", "{$origen}.distancia_km", 's.tarifa_hectarea', 's.monto_estimado', 's.estado as solicitud_estado')
            ->orderBy("{$origen}.id")
            ->get();

        foreach ($aceptadas as $fila) {
            if (DB::table('neg_ofertas')->where('solicitud_id', $fila->solicitud_id)->where('estado', 'aceptada')->exists()) {
                continue;
            }

            $ahora = now();
            $id = DB::table('neg_ofertas')->insertGetId([
                'uuid_cliente' => (string) Str::uuid(),
                'solicitud_id' => $fila->solicitud_id,
                'proveedor_id' => $fila->proveedor_id,
                'equipo_id' => $fila->equipo_id,
                'numero' => 1,
                'autor' => 'proveedor',
                'tipo' => 'acepta_propuesta',
                'precio_ha' => $fila->tarifa_hectarea,
                'monto_estimado' => $fila->monto_estimado ?? '0',
                'estado' => 'aceptada',
                'es_definitiva' => false,
                'vence_en' => $fila->respondida_en ?? $ahora,
                'respondida_en' => $fila->respondida_en,
                'distancia_km' => $fila->distancia_km,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);

            DB::table('sol_solicitudes_servicio')->where('id', $fila->solicitud_id)->update(['oferta_adjudicada_id' => $id]);
        }
    }

    private function respaldarYQuitarComision(): void
    {
        $destino = $this->nombre('respaldo_comision', 'sol_solicitudes_comision');
        $prefijo = DB::getTablePrefix();

        DB::statement("CREATE TABLE {$destino} AS SELECT id AS solicitud_id, comision_porcentaje, comision_monto FROM {$prefijo}sol_solicitudes_servicio");

        Schema::table('sol_solicitudes_servicio', function (Blueprint $table) {
            $table->dropColumn(['comision_porcentaje', 'comision_monto']);
        });
    }

    private function restaurarComision(): void
    {
        Schema::table('sol_solicitudes_servicio', function (Blueprint $table) {
            $table->decimal('comision_porcentaje', 5, 2)->nullable();
            $table->decimal('comision_monto', 14, 2)->nullable();
        });

        $origen = $this->nombre('respaldo_comision', 'sol_solicitudes_comision');
        $prefijo = DB::getTablePrefix();

        DB::statement("UPDATE {$prefijo}sol_solicitudes_servicio SET comision_porcentaje = r.comision_porcentaje, comision_monto = r.comision_monto FROM {$origen} r WHERE r.solicitud_id = {$prefijo}sol_solicitudes_servicio.id");
        DB::statement("DROP TABLE {$origen}");
    }

    private function restaurarEstados(): void
    {
        $prefijo = DB::getTablePrefix();
        $solicitudes = $this->nombre('respaldo_estados', 'sol_solicitudes_servicio');
        $historial = $this->nombre('respaldo_estados', 'sol_solicitud_historial');

        if ($this->pg()) {
            DB::statement("ALTER TABLE {$prefijo}sol_solicitudes_servicio DROP CONSTRAINT {$prefijo}sol_solicitudes_servicio_estado_chk");
        }

        DB::statement("UPDATE {$prefijo}sol_solicitudes_servicio SET estado = r.estado FROM {$solicitudes} r WHERE r.id = {$prefijo}sol_solicitudes_servicio.id");
        DB::statement("UPDATE {$prefijo}sol_solicitud_historial SET estado_anterior = r.estado_anterior, estado_nuevo = r.estado_nuevo FROM {$historial} r WHERE r.id = {$prefijo}sol_solicitud_historial.id");

        // Una solicitud creada después de la migración no tiene respaldo: cae al estado más cercano.
        foreach (['abierta' => 'publicada', 'con_ofertas' => 'en_asignacion', 'adjudicada' => 'asignada', 'finalizada' => 'completada', 'calificada' => 'completada', 'expirada' => 'sin_proveedor', 'disputada' => 'en_disputa'] as $nuevo => $viejo) {
            DB::table('sol_solicitudes_servicio')->where('estado', $nuevo)->update(['estado' => $viejo]);
            DB::table('sol_solicitud_historial')->where('estado_nuevo', $nuevo)->update(['estado_nuevo' => $viejo]);
            DB::table('sol_solicitud_historial')->where('estado_anterior', $nuevo)->update(['estado_anterior' => $viejo]);
        }

        if ($this->pg()) {
            $lista = $this->lista(self::ESTADOS_ANTERIORES);
            DB::statement("ALTER TABLE {$prefijo}sol_solicitudes_servicio ADD CONSTRAINT {$prefijo}sol_solicitudes_servicio_estado_chk CHECK (estado IN ({$lista}))");
        }

        DB::statement("DROP TABLE {$solicitudes}");
        DB::statement("DROP TABLE {$historial}");
    }

    /** @param  list<string>  $valores */
    private function lista(array $valores): string
    {
        return implode(', ', array_map(static fn (string $v): string => "'{$v}'", $valores));
    }
};
