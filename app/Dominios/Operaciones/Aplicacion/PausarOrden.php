<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosOrden;
use App\Dominios\Operaciones\Dominio\Excepciones\MotivoRequerido;
use App\Dominios\Operaciones\Dominio\Excepciones\TransicionOrdenNoPermitida;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;

/**
 * Pausa de una aplicación desde el panel (ADR 0022): adaptador delgado sobre
 * {@see MaquinaEstadosOrden::pausar()} — la decide el operador con el informe
 * del equipo a la vista, nunca la app de campo. Ninguna regla vive acá
 * (invariante 7).
 */
final class PausarOrden
{
    public function __construct(private readonly MaquinaEstadosOrden $maquinaEstados) {}

    /**
     * @throws MotivoRequerido si el motivo viene vacío.
     * @throws TransicionOrdenNoPermitida si `$orden` no está `vigente`.
     */
    public function ejecutar(OrdenAplicacion $orden, string $motivo): OrdenAplicacion
    {
        return $this->maquinaEstados->pausar($orden, $motivo);
    }
}
