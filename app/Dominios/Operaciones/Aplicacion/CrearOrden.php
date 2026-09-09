<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosOrden;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;

/**
 * Alta de una orden de aplicación desde el panel (HU-25, tarea 38): adaptador
 * delgado sobre {@see MaquinaEstadosOrden::crear()} — toda orden nueva nace
 * `emitida` (invariante 7), ninguna regla de negocio vive acá.
 */
final class CrearOrden
{
    public function __construct(private readonly MaquinaEstadosOrden $maquinaEstados) {}

    /** @param  array<string, mixed>  $atributos  sin `estado`: lo fija la máquina de estados. */
    public function ejecutar(array $atributos): OrdenAplicacion
    {
        return $this->maquinaEstados->crear($atributos);
    }
}
