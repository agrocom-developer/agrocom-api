<?php

namespace App\Dominios\Personal\Aplicacion;

use App\Dominios\Personal\Aplicacion\MaquinaEstados\MaquinaEstadosEquipoTrabajo;
use App\Dominios\Personal\Dominio\EstadoEquipoTrabajo;
use App\Dominios\Personal\Dominio\Excepciones\TransicionEquipoTrabajoNoPermitida;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoTrabajo;

/**
 * Cambio de estado de un equipo de trabajo desde el panel (tarea
 * "cuadrillas-estadias", 19/9/2026, pedido del dueño de dibujar los pasos
 * `step-arrow` en la ficha): adaptador delgado sobre
 * {@see MaquinaEstadosEquipoTrabajo::cambiarA()} — ninguna regla de negocio
 * vive acá, solo delega (invariante 7). Mismo criterio que
 * `Campania\Aplicacion\CambiarEstadoCampania`.
 */
final class CambiarEstadoEquipoTrabajo
{
    public function __construct(private readonly MaquinaEstadosEquipoTrabajo $maquinaEstados) {}

    /** @throws TransicionEquipoTrabajoNoPermitida si la transición no está permitida. */
    public function ejecutar(EquipoTrabajo $equipo, EstadoEquipoTrabajo $hacia): EquipoTrabajo
    {
        return $this->maquinaEstados->cambiarA($equipo, $hacia);
    }
}
