<?php

namespace App\Dominios\Mantenimiento\Dominio;

/**
 * Estado descriptivo de un vehículo de la flota (HU-40, tarea 50;
 * `man_vehiculos.estado`, CHECK en la migración). NO es una máquina de
 * estados de negocio: no hay guardas ni transiciones gobernadas por una
 * regla del dominio (CLAUDE.md invariante 7 no aplica acá) — es una
 * clasificación libre, mismo criterio que `RolOperativoPersona` en
 * `Personal`. Cualquier valor puede pasar a cualquier otro sin política.
 *
 * `Pausa` (HU-84, tarea 99): baja TEMPORAL de servicio, distinta de
 * `Taller` (en reparación) y de `DeBaja` (definitiva) — mismo criterio de
 * "sin guardas" que `EstadoBateria::Mantenimiento`: entra y sale de este
 * valor libremente, sin máquina de estados.
 */
enum EstadoVehiculo: string
{
    case Activo = 'activo';
    case Taller = 'taller';
    case DeBaja = 'de_baja';
    case Pausa = 'pausa';
}
