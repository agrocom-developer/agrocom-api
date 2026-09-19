<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosOrden;
use App\Dominios\Operaciones\Dominio\CausaCancelacionOrden;
use App\Dominios\Operaciones\Dominio\Excepciones\MotivoRequerido;
use App\Dominios\Operaciones\Dominio\Excepciones\TransicionOrdenNoPermitida;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;

/**
 * Cancelación de una aplicación desde el panel (ADR 0022): adaptador delgado
 * sobre {@see MaquinaEstadosOrden::cancelar()}. Exclusiva del panel: la app de
 * campo nunca cancela, solo registra lo que pasa; el operador decide junto con
 * el dueño. Ninguna regla vive acá (invariante 7).
 */
final class CancelarOrden
{
    public function __construct(private readonly MaquinaEstadosOrden $maquinaEstados) {}

    /**
     * @throws MotivoRequerido si el motivo viene vacío.
     * @throws TransicionOrdenNoPermitida si `$orden` no está `vigente` ni `pausada`.
     */
    public function ejecutar(OrdenAplicacion $orden, CausaCancelacionOrden $causa, string $motivo): OrdenAplicacion
    {
        return $this->maquinaEstados->cancelar($orden, $causa, $motivo);
    }
}
