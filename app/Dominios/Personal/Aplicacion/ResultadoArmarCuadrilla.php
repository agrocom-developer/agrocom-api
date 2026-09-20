<?php

namespace App\Dominios\Personal\Aplicacion;

use App\Dominios\Personal\Infraestructura\Eloquent\EquipoTrabajo;

/**
 * Resultado de {@see ArmarCuadrilla}: la cuadrilla recién creada, con todos
 * sus integrantes y su equipamiento ya asignados, más los equipos que avisan
 * solapamiento — el mismo piloto, ayudante o recurso vigente en OTRA
 * cuadrilla en fechas que se pisan (préstamo real entre cuadrillas, ver
 * `Dominio\ResultadoSolapamientoVigencias`). Vive en `Aplicacion/`, no en
 * `Dominio/`, porque referencia el modelo Eloquent de la cuadrilla (ADR 0003
 * regla 4: Eloquent ES el modelo de dominio dentro del módulo dueño, pero
 * `Dominio/` en sí se mantiene libre de Eloquent).
 */
final readonly class ResultadoArmarCuadrilla
{
    /** @param  list<int>  $equiposEnAviso */
    public function __construct(
        public EquipoTrabajo $equipo,
        public array $equiposEnAviso,
    ) {}

    public function tieneAviso(): bool
    {
        return $this->equiposEnAviso !== [];
    }
}
