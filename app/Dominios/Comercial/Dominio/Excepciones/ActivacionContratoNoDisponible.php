<?php

namespace App\Dominios\Comercial\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use Carbon\CarbonImmutable;
use DomainException;

/**
 * Guarda de negocio de la transición `borrador → vigente` (HU-23, tarea 34)
 * que la tabla de {@see
 * \App\Dominios\Comercial\Dominio\MaquinaEstados\TransicionesContrato} no
 * puede expresar por sí sola, porque depende de los DATOS del contrato, no
 * solo de su estado actual — ver el docblock de
 * `Aplicacion/MaquinaEstados/MaquinaEstadosContrato::activar()` para el
 * porqué.
 */
final class ActivacionContratoNoDisponible extends DomainException
{
    public static function porFechaInicioEnElPasado(int $contratoId, CarbonImmutable $fechaInicio): self
    {
        return new self(Texto::de('comercial.errores.contrato_fecha_inicio_pasada', [
            'id' => $contratoId,
            'fecha' => $fechaInicio->toDateString(),
        ]));
    }
}
