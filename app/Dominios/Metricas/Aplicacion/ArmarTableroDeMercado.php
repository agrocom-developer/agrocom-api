<?php

namespace App\Dominios\Metricas\Aplicacion;

use App\Dominios\Metricas\Contratos\LecturaTableroDeMercado;
use App\Dominios\Metricas\Dominio\GruposDeSolicitud;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * El tablero del panel (especificación §14): solicitudes por estado y por departamento
 * (el de la solicitud, o el de su terreno mientras es borrador) y la cola de cosas que esperan una
 * decisión del administrador. Solo lectura sobre las tablas de los módulos
 * dueños (ADR 0012); las hectáreas viajan como decimal exacto en texto
 * (invariante 6 de CLAUDE.md).
 */
final class ArmarTableroDeMercado implements LecturaTableroDeMercado
{
    /** Orden del ciclo de una solicitud, para que el tablero se lea de izquierda a derecha. */
    private const ESTADOS = ['borrador', 'abierta', 'con_ofertas', 'adjudicada', 'en_ejecucion', 'finalizada', 'calificada', 'en_reclamo', 'cancelada'];

    /** Semanas de la serie de actividad del tablero, la actual incluida. */
    public const SEMANAS = 12;

    /** Departamento de una solicitud sin departamento (un borrador cuyo terreno aún no lo tiene). */
    public const SIN_DEPARTAMENTO = 'sin_departamento';

    public function tablero(): array
    {
        $filas = DB::table('sol_solicitudes_servicio as s')
            ->leftJoin('sol_terrenos as t', 't.id', '=', 's.terreno_id')
            ->leftJoin('sol_departamentos as d', 'd.codigo', '=', DB::raw('COALESCE(s.departamento_codigo, t.departamento_codigo)'))
            ->whereNull('s.deleted_at')
            ->selectRaw('s.estado as estado, d.codigo as departamento_codigo, d.nombre as departamento, COUNT(*) as cantidad, SUM(s.hectareas_solicitadas) as hectareas')
            ->groupBy('s.estado', 'd.codigo', 'd.nombre')
            ->get();

        $estados = array_fill_keys(self::ESTADOS, ['cantidad' => 0, 'hectareas' => BigDecimal::zero()]);
        $departamentos = [];
        $enMercado = BigDecimal::zero();

        foreach ($filas as $fila) {
            $estado = (string) $fila->estado;
            $codigo = $fila->departamento_codigo === null || $fila->departamento_codigo === '' ? self::SIN_DEPARTAMENTO : (string) $fila->departamento_codigo;
            $hectareas = BigDecimal::of((string) ($fila->hectareas ?? '0'));

            $actual = $estados[$estado] ?? ['cantidad' => 0, 'hectareas' => BigDecimal::zero()];
            $estados[$estado] = ['cantidad' => $actual['cantidad'] + (int) $fila->cantidad, 'hectareas' => $actual['hectareas']->plus($hectareas)];

            $departamentos[$codigo] ??= ['nombre' => $fila->departamento === null ? null : (string) $fila->departamento, 'abiertas' => 0, 'en_curso' => 0, 'cerradas' => 0, 'canceladas' => 0, 'total' => 0, 'hectareas' => BigDecimal::zero()];
            $grupo = GruposDeSolicitud::de($estado);
            if ($grupo !== null) {
                $departamentos[$codigo][$grupo] += (int) $fila->cantidad;
            }
            $departamentos[$codigo]['total'] += (int) $fila->cantidad;

            if ($grupo === GruposDeSolicitud::ABIERTAS) {
                $departamentos[$codigo]['hectareas'] = $departamentos[$codigo]['hectareas']->plus($hectareas);
                $enMercado = $enMercado->plus($hectareas);
            }
        }

        uasort($departamentos, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        return [
            'por_estado' => array_map(
                static fn (string $estado): array => [
                    'estado' => $estado,
                    'cantidad' => $estados[$estado]['cantidad'],
                    'hectareas' => (string) $estados[$estado]['hectareas']->toScale(2, RoundingMode::HalfUp),
                ],
                self::ESTADOS,
            ),
            'total_solicitudes' => array_sum(array_column($estados, 'cantidad')),
            'hectareas_en_mercado' => (string) $enMercado->toScale(2, RoundingMode::HalfUp),
            'por_departamento' => array_map(
                static fn (string $codigo, array $datos): array => [
                    'departamento_codigo' => $codigo,
                    'departamento' => $datos['nombre'],
                    ...array_diff_key($datos, ['nombre' => true]),
                    'hectareas' => (string) $datos['hectareas']->toScale(2, RoundingMode::HalfUp),
                ],
                array_keys($departamentos),
                array_values($departamentos),
            ),
            'por_semana' => $this->porSemana(),
            'pendientes' => $this->pendientes(),
        ];
    }

    /**
     * La actividad de las últimas {@see self::SEMANAS} semanas (de lunes a domingo, la actual incluida):
     * solicitudes publicadas, solicitudes adjudicadas y el precio por hectárea acordado, promedio
     * ponderado por hectáreas de lo adjudicado esa semana (`null` si no se adjudicó nada con precio).
     * Todas las semanas aparecen, también las vacías, para que la línea de tiempo no se salte ninguna.
     *
     * @return list<array{semana: string, publicadas: int, adjudicadas: int, precio_ha_medio: ?string}>
     */
    private function porSemana(): array
    {
        $primera = Carbon::now()->startOfWeek()->subWeeks(self::SEMANAS - 1);

        $semanas = [];
        for ($i = 0; $i < self::SEMANAS; $i++) {
            $semanas[$primera->copy()->addWeeks($i)->toDateString()] = ['publicadas' => 0, 'adjudicadas' => 0, 'monto' => BigDecimal::zero(), 'hectareas' => BigDecimal::zero()];
        }
        $semanaDe = static fn (string $dia): string => Carbon::parse($dia)->startOfWeek()->toDateString();

        $publicadas = DB::table('sol_solicitudes_servicio')
            ->whereNull('deleted_at')->where('publicada_en', '>=', $primera)
            ->selectRaw('DATE(publicada_en) as dia, COUNT(*) as cantidad')->groupByRaw('DATE(publicada_en)')->get();
        foreach ($publicadas as $fila) {
            $semanas[$semanaDe((string) $fila->dia)]['publicadas'] += (int) $fila->cantidad;
        }

        $adjudicadas = DB::table('sol_solicitudes_servicio')
            ->whereNull('deleted_at')->where('adjudicada_en', '>=', $primera)
            ->selectRaw('DATE(adjudicada_en) as dia, COUNT(*) as cantidad, COALESCE(SUM(precio_ha_acordado * hectareas_solicitadas), 0) as monto, COALESCE(SUM(CASE WHEN precio_ha_acordado IS NULL THEN 0 ELSE hectareas_solicitadas END), 0) as hectareas')
            ->groupByRaw('DATE(adjudicada_en)')->get();
        foreach ($adjudicadas as $fila) {
            $semana = $semanaDe((string) $fila->dia);
            $semanas[$semana]['adjudicadas'] += (int) $fila->cantidad;
            $semanas[$semana]['monto'] = $semanas[$semana]['monto']->plus(BigDecimal::of((string) $fila->monto));
            $semanas[$semana]['hectareas'] = $semanas[$semana]['hectareas']->plus(BigDecimal::of((string) $fila->hectareas));
        }

        return array_map(
            static fn (string $semana, array $datos): array => [
                'semana' => $semana,
                'publicadas' => $datos['publicadas'],
                'adjudicadas' => $datos['adjudicadas'],
                'precio_ha_medio' => $datos['hectareas']->isZero() ? null : (string) $datos['monto']->dividedBy($datos['hectareas'], 2, RoundingMode::HalfUp),
            ],
            array_keys($semanas),
            array_values($semanas),
        );
    }

    /** @return array<string, int> lo que espera una decisión del administrador, por tipo. */
    private function pendientes(): array
    {
        $vivos = static fn (string $tabla) => DB::table($tabla)->whereNull('deleted_at');

        return [
            'proveedores' => $vivos('prv_proveedores')->where('estado', 'pendiente')->count(),
            'pilotos' => $vivos('prv_pilotos')->where('estado', 'pendiente')->count(),
            'drones' => $vivos('flo_drones')->where('estado', 'pendiente')->count(),
            'fotos_de_dron' => $vivos('flo_dron_fotos')->where('estado', 'en_revision')->count(),
            'documentos' => $vivos('prv_documentos')->where('estado', 'en_revision')->count(),
            'calificaciones' => $vivos('cal_calificaciones')->where('estado', 'en_moderacion')->count(),
            'reclamos' => $vivos('rcl_reclamos')->whereIn('estado', ['abierta', 'en_revision'])->count(),
        ];
    }
}
