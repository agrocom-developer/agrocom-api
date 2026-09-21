<?php

namespace App\Dominios\Personal\Dominio;

/**
 * Estado de un equipo de trabajo (tarea 72, HU-49; ampliado por la tarea
 * "cuadrillas-estadias", 19/9/2026; `per_equipos_trabajo.estado`, CHECK en la
 * migración). Ya NO es un campo descriptivo libre: desde el pedido del dueño
 * de dibujar los pasos `step-arrow` en la ficha, es una máquina de estados
 * real, ida y vuelta (`activo ⇄ inactivo` — una cuadrilla dada de baja se
 * puede reactivar), gobernada por
 * `Dominio\MaquinaEstados\TransicionesEquipoTrabajo` y escrita únicamente por
 * `Aplicacion\MaquinaEstados\MaquinaEstadosEquipoTrabajo` (invariante 7 de
 * CLAUDE.md). `CrearEquipoTrabajo`/`ActualizarEquipoTrabajo` ya no reciben
 * `estado`: una cuadrilla nace `activo` y la edición no lo toca — el cambio
 * de estado tiene su propio caso de uso, `CambiarEstadoEquipoTrabajo`.
 */
enum EstadoEquipoTrabajo: string
{
    case Activo = 'activo';
    case Inactivo = 'inactivo';
}
