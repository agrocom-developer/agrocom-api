<?php

namespace App\Dominios\Metricas\Aplicacion\Bloques;

use App\Dominios\Metricas\Contratos\LecturaMetricasMercado;
use App\Dominios\Metricas\Dominio\GruposDeSolicitud;
use App\Dominios\Metricas\Dominio\PeriodoDeMetricas;
use App\Dominios\Metricas\Dominio\Razones;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/** Bloque «mercado»: solicitudes, adjudicación, tiempo hasta adjudicar y rondas de negociación. */
final class BloqueMercado
{
    /** Extremos de los tramos del tiempo hasta adjudicar, en minutos: 1 hora, 6 horas, 1 día y 3 días. */
    public const TRAMOS_DE_TIEMPO = [60, 360, 1440, 4320];

    /** Extremos de los tramos de rondas por negociación: 1, 2, 3 y 4 o más. */
    public const TRAMOS_DE_RONDAS = [2, 3, 4];

    public function __construct(private readonly LecturaMetricasMercado $lectura) {}

    /** @return array<string, mixed> */
    public function armar(PeriodoDeMetricas $periodo): array
    {
        $solicitudes = $this->lectura->solicitudes($periodo);
        $minutos = $this->lectura->minutosHastaAdjudicacion($periodo);
        $negociaciones = $this->lectura->negociaciones($periodo);
        $total = $solicitudes['total'];
        $finalizadas = (int) (($solicitudes['por_estado']['finalizada'] ?? 0) + ($solicitudes['por_estado']['calificada'] ?? 0));

        return [
            'solicitudes_publicadas' => $total,
            'por_estado' => $solicitudes['por_estado'],
            'por_departamento' => $solicitudes['por_departamento'],
            'adjudicadas' => $solicitudes['adjudicadas'],
            'tasa_adjudicacion' => Razones::tasa($solicitudes['adjudicadas'], $total),
            'abiertas' => (int) (($solicitudes['por_estado']['abierta'] ?? 0) + ($solicitudes['por_estado']['con_ofertas'] ?? 0)),
            'hectareas_solicitadas' => $solicitudes['hectareas_solicitadas'],
            'hectareas_servidas' => $solicitudes['hectareas_servidas'],
            'tiempo_aceptacion_promedio_min' => Razones::promedio($minutos),
            'tiempo_aceptacion_mediana_min' => Razones::mediana($minutos),
            'aceptaciones' => count($minutos),
            'ofertas_por_solicitud' => $total === 0 ? null : (string) BigDecimal::of($negociaciones['ofertas'])->dividedBy($total, 2, RoundingMode::HalfUp),
            'rondas_promedio' => Razones::promedio($negociaciones['rondas']),
            'negociaciones' => count($negociaciones['rondas']),
            // Cuántas solicitudes llegaron al menos hasta cada paso. Una adjudicada siempre tuvo oferta
            // aceptada: si una solicitud migrada no conserva sus ofertas, el paso no baja del siguiente.
            'embudo' => [
                'publicadas' => $total,
                'con_ofertas' => max($this->lectura->conOfertas($periodo), $solicitudes['adjudicadas']),
                'adjudicadas' => $solicitudes['adjudicadas'],
                'finalizadas' => $finalizadas,
            ],
            'distribucion_tiempo' => Razones::distribuir($minutos, self::TRAMOS_DE_TIEMPO),
            'distribucion_rondas' => Razones::distribuir($negociaciones['rondas'], self::TRAMOS_DE_RONDAS),
            'departamentos' => $this->departamentos($periodo),
        ];
    }

    /**
     * Las solicitudes del período por departamento y por grupo de estado, de más a menos.
     *
     * @return list<array{codigo: string, nombre: ?string, abiertas: int, en_curso: int, cerradas: int, canceladas: int, total: int}>
     */
    private function departamentos(PeriodoDeMetricas $periodo): array
    {
        $departamentos = [];

        foreach ($this->lectura->porDepartamentoYEstado($periodo) as $fila) {
            $departamentos[$fila['codigo']] ??= ['codigo' => $fila['codigo'], 'nombre' => $fila['nombre'], 'abiertas' => 0, 'en_curso' => 0, 'cerradas' => 0, 'canceladas' => 0, 'total' => 0];
            $grupo = GruposDeSolicitud::de($fila['estado']);
            if ($grupo !== null) {
                $departamentos[$fila['codigo']][$grupo] += $fila['cantidad'];
            }
            $departamentos[$fila['codigo']]['total'] += $fila['cantidad'];
        }

        usort($departamentos, static fn (array $a, array $b): int => [$b['total'], $a['codigo']] <=> [$a['total'], $b['codigo']]);

        return $departamentos;
    }
}
