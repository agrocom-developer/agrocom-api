<?php

namespace App\Dominios\Mantenimiento\Dominio;

/**
 * Estado descriptivo de una batería del catálogo (HU-39, tarea 51;
 * `man_baterias.estado`, CHECK en la migración). NO es una máquina de
 * estados de negocio: no hay guardas ni transiciones gobernadas por una
 * regla del dominio (CLAUDE.md invariante 7 no aplica acá) — mismo
 * criterio que `EstadoVehiculo`. Cualquier valor puede pasar a cualquier
 * otro sin política; retirar una batería es una decisión del encargado
 * ante la alerta de ciclos/temperatura, no un flujo gobernado.
 */
enum EstadoBateria: string
{
    case Activa = 'activa';
    case Retirada = 'retirada';
}
