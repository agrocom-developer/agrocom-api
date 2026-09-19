<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Aplicacion\MaquinaEstados\MaquinaEstadosContrato;
use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Dominio\Excepciones\ActivacionContratoNoDisponible;
use App\Dominios\Comercial\Dominio\Excepciones\ContratoConAplicacionAbierta;
use App\Dominios\Comercial\Dominio\Excepciones\TransicionContratoNoPermitida;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;

/**
 * Cambio de estado de un contrato desde el panel (HU-23, tarea 34): adaptador
 * delgado sobre {@see MaquinaEstadosContrato::cambiarA()} — ninguna regla de
 * negocio vive acá, solo delega (invariante 7).
 */
final class CambiarEstadoContrato
{
    public function __construct(private readonly MaquinaEstadosContrato $maquinaEstados) {}

    /**
     * @throws TransicionContratoNoPermitida si la transición no está permitida.
     * @throws ActivacionContratoNoDisponible si `$hacia` es `vigente` y falta alguna guarda.
     * @throws ContratoConAplicacionAbierta si `$hacia` es `finalizado`/`cancelado` y el contrato tiene una aplicación abierta.
     */
    public function ejecutar(Contrato $contrato, EstadoContrato $hacia): Contrato
    {
        return $this->maquinaEstados->cambiarA($contrato, $hacia);
    }
}
