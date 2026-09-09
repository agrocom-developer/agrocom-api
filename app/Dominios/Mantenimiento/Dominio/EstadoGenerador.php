<?php

namespace App\Dominios\Mantenimiento\Dominio;

/**
 * Estado descriptivo de un generador de catálogo (tarea 72, HU-49;
 * `man_generadores.estado`, CHECK en la migración). NO es una máquina de
 * estados de negocio: no hay guardas ni transiciones gobernadas por una
 * regla del dominio (CLAUDE.md invariante 7 no aplica acá) — mismo criterio
 * que `EstadoVehiculo`. Cualquier valor puede pasar a cualquier otro sin
 * política.
 */
enum EstadoGenerador: string
{
    case Activo = 'activo';
    case Taller = 'taller';
    case DeBaja = 'de_baja';
}
