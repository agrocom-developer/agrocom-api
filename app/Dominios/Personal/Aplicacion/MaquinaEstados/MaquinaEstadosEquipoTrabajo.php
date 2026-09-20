<?php

namespace App\Dominios\Personal\Aplicacion\MaquinaEstados;

use App\Dominios\Personal\Dominio\EstadoEquipoTrabajo;
use App\Dominios\Personal\Dominio\Excepciones\TransicionEquipoTrabajoNoPermitida;
use App\Dominios\Personal\Dominio\MaquinaEstados\TransicionesEquipoTrabajo;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoTrabajo;

/**
 * Única clase que crea/muta el `estado` de un equipo de trabajo (invariante 7
 * de CLAUDE.md; tarea "cuadrillas-estadias", 19/9/2026), mismo criterio que
 * `MaquinaEstadosCampania`.
 *
 * `crear()` fija siempre `activo`: una cuadrilla nace activa, nunca es un
 * dato que decida quien completa el formulario de alta.
 */
final class MaquinaEstadosEquipoTrabajo
{
    /** @param  array<string, mixed>  $atributos  sin `estado`: lo fija esta clase. */
    public function crear(array $atributos): EquipoTrabajo
    {
        return EquipoTrabajo::create([...$atributos, 'estado' => EstadoEquipoTrabajo::Activo]);
    }

    /** @throws TransicionEquipoTrabajoNoPermitida si `$equipo` no está `inactivo`. */
    public function activar(EquipoTrabajo $equipo): EquipoTrabajo
    {
        return $this->transicionar($equipo, EstadoEquipoTrabajo::Activo);
    }

    /** @throws TransicionEquipoTrabajoNoPermitida si `$equipo` no está `activo`. */
    public function desactivar(EquipoTrabajo $equipo): EquipoTrabajo
    {
        return $this->transicionar($equipo, EstadoEquipoTrabajo::Inactivo);
    }

    /**
     * Punto de entrada único para el controlador HTTP: resuelve a qué método
     * de transición corresponde `$hacia` sin que el llamador tenga que
     * conocer el nombre de cada uno.
     *
     * @throws TransicionEquipoTrabajoNoPermitida si la transición no está en la tabla.
     */
    public function cambiarA(EquipoTrabajo $equipo, EstadoEquipoTrabajo $hacia): EquipoTrabajo
    {
        return match ($hacia) {
            EstadoEquipoTrabajo::Activo => $this->activar($equipo),
            EstadoEquipoTrabajo::Inactivo => $this->desactivar($equipo),
        };
    }

    /** @throws TransicionEquipoTrabajoNoPermitida si la transición no está permitida. */
    private function transicionar(EquipoTrabajo $equipo, EstadoEquipoTrabajo $hasta): EquipoTrabajo
    {
        $desde = $equipo->estado;

        if (! TransicionesEquipoTrabajo::permitida($desde, $hasta)) {
            throw TransicionEquipoTrabajoNoPermitida::entre($desde, $hasta);
        }

        $equipo->estado = $hasta;
        $equipo->save();

        return $equipo;
    }
}
