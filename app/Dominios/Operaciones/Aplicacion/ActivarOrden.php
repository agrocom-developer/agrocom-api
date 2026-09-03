<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosOrden;
use App\Dominios\Operaciones\Dominio\Excepciones\OrdenVigenteDuplicadaEnLote;
use App\Dominios\Operaciones\Dominio\Excepciones\TransicionOrdenNoPermitida;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;

/**
 * Activación de una orden desde el panel (HU-25, tarea 38): adaptador delgado
 * sobre {@see MaquinaEstadosOrden::activar()} — ninguna regla de negocio vive
 * acá, solo delega (invariante 7).
 */
final class ActivarOrden
{
    public function __construct(private readonly MaquinaEstadosOrden $maquinaEstados) {}

    /**
     * @throws TransicionOrdenNoPermitida si `$orden` no está `emitida`.
     * @throws OrdenVigenteDuplicadaEnLote si el lote ya tiene otra orden vigente.
     */
    public function ejecutar(OrdenAplicacion $orden): OrdenAplicacion
    {
        return $this->maquinaEstados->activar($orden);
    }
}
