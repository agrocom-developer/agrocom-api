<?php

namespace App\Dominios\Operaciones\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use App\Dominios\Operaciones\Dominio\ImpedimentoCierreOrden;
use App\Dominios\Operaciones\Dominio\PoliticaCierreOrden;
use DomainException;

/**
 * La orden todavía no se puede cerrar (`vigente → consumida`): no tiene ninguna
 * orden de trabajo, le quedan hectáreas sin asignar o algún equipo no terminó sus
 * trabajos. Lo lanza
 * `MaquinaEstadosOrden::cerrar()` con la regla de
 * {@see PoliticaCierreOrden}.
 */
final class CierreOrdenNoPermitido extends DomainException
{
    public static function por(ImpedimentoCierreOrden $impedimento, int $abiertos = 0, string $hectareasSinAsignar = '0'): self
    {
        return new self(match ($impedimento) {
            ImpedimentoCierreOrden::SinTrabajos => Texto::de('operaciones.errores.cierre_orden_sin_trabajos'),
            ImpedimentoCierreOrden::HectareasSinAsignar => Texto::de('operaciones.errores.cierre_orden_hectareas_sin_asignar', ['hectareas' => $hectareasSinAsignar]),
            ImpedimentoCierreOrden::TrabajosAbiertos => Texto::de('operaciones.errores.cierre_orden_trabajos_abiertos', ['cantidad' => $abiertos]),
        });
    }
}
