<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosOrden;
use App\Dominios\Operaciones\Dominio\Excepciones\TransicionOrdenNoPermitida;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;

/**
 * Reanudación de una aplicación pausada (ADR 0022): adaptador delgado sobre
 * {@see MaquinaEstadosOrden::reanudar()} — ninguna regla vive acá (invariante 7).
 */
final class ReanudarOrden
{
    public function __construct(private readonly MaquinaEstadosOrden $maquinaEstados) {}

    /** @throws TransicionOrdenNoPermitida si `$orden` no está `pausada`. */
    public function ejecutar(OrdenAplicacion $orden): OrdenAplicacion
    {
        return $this->maquinaEstados->reanudar($orden);
    }
}
