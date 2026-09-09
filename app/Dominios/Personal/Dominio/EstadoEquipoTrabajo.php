<?php

namespace App\Dominios\Personal\Dominio;

/**
 * Estado descriptivo de un equipo de trabajo (tarea 72, HU-49;
 * `per_equipos_trabajo.estado`, CHECK en la migración). NO es una máquina de
 * estados de negocio: no hay guardas ni transiciones gobernadas por una
 * regla del dominio (CLAUDE.md invariante 7 no aplica acá) — mismo criterio
 * que `EstadoVehiculo`/`EstadoGenerador`.
 */
enum EstadoEquipoTrabajo: string
{
    case Activo = 'activo';
    case Inactivo = 'inactivo';
}
