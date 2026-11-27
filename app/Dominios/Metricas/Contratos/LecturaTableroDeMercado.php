<?php

namespace App\Dominios\Metricas\Contratos;

/**
 * Lectura del tablero del panel: cómo está el mercado ahora, por estado de la
 * solicitud y por región, la actividad de las últimas semanas y qué espera una decisión del administrador. Contrato
 * de solo lectura que otros módulos (el tablero de Seguridad) consumen sin
 * conocer las tablas de los módulos dueños.
 */
interface LecturaTableroDeMercado
{
    /**
     * @return array{
     *     por_estado: list<array{estado: string, cantidad: int, hectareas: string}>,
     *     total_solicitudes: int,
     *     hectareas_en_mercado: string,
     *     por_departamento: list<array{departamento_codigo: string, departamento: ?string, abiertas: int, en_curso: int, cerradas: int, canceladas: int, total: int, hectareas: string}>,
     *     por_semana: list<array{semana: string, publicadas: int, adjudicadas: int, precio_ha_medio: ?string}>,
     *     pendientes: array<string, int>
     * }
     */
    public function tablero(): array;
}
