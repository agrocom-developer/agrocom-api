<?php

namespace App\Dominios\Metricas\Contratos;

use App\Dominios\Metricas\Dominio\PeriodoDeMetricas;

/**
 * Lectura de las cifras de mercado de un período: solicitudes publicadas,
 * tiempos de adjudicación y rondas de negociación. Solo cifras crudas (conteos,
 * sumas DECIMAL como cadena, minutos); las razones las arma el caso de uso.
 */
interface LecturaMetricasMercado
{
    /**
     * @return array{
     *     total: int,
     *     adjudicadas: int,
     *     por_estado: array<string, int>,
     *     por_departamento: array<string, int>,
     *     hectareas_solicitadas: string,
     *     hectareas_servidas: string
     * }
     */
    public function solicitudes(PeriodoDeMetricas $periodo): array;

    /** @return list<float> minutos de la publicación a la adjudicación, de las adjudicadas en el período. */
    public function minutosHastaAdjudicacion(PeriodoDeMetricas $periodo): array;

    /** @return array{ofertas: int, rondas: list<int>} ofertas de las solicitudes del período y rondas por (solicitud, proveedor). */
    public function negociaciones(PeriodoDeMetricas $periodo): array;

    /** @return int solicitudes del período que recibieron al menos una oferta. */
    public function conOfertas(PeriodoDeMetricas $periodo): int;

    /** @return list<array{codigo: string, nombre: ?string, estado: string, cantidad: int}> solicitudes del período por departamento y estado. */
    public function porDepartamentoYEstado(PeriodoDeMetricas $periodo): array;
}
