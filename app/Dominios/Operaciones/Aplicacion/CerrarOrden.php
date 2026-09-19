<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosOrden;
use App\Dominios\Operaciones\Dominio\Excepciones\TransicionOrdenNoPermitida;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;

/**
 * Cierre de una aplicación cumplida (ADR 0022): adaptador delgado sobre
 * {@see MaquinaEstadosOrden::cerrar()} — acción manual del encargado, con el
 * informe del equipo a la vista. Ninguna regla vive acá (invariante 7); el
 * cierre de la última aplicación finaliza el contrato por evento, no desde acá.
 */
final class CerrarOrden
{
    public function __construct(private readonly MaquinaEstadosOrden $maquinaEstados) {}

    /** @throws TransicionOrdenNoPermitida si `$orden` no está `vigente`. */
    public function ejecutar(OrdenAplicacion $orden): OrdenAplicacion
    {
        return $this->maquinaEstados->cerrar($orden);
    }
}
