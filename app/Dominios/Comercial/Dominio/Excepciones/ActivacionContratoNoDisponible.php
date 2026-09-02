<?php

namespace App\Dominios\Comercial\Dominio\Excepciones;

use Carbon\CarbonImmutable;
use DomainException;

/**
 * Guardas de negocio de la transición `borrador → vigente` (HU-23, tarea 34)
 * que la tabla de {@see
 * \App\Dominios\Comercial\Dominio\MaquinaEstados\TransicionesContrato} no
 * puede expresar por sí sola, porque dependen de los DATOS del contrato, no
 * solo de su estado actual — ver el docblock de
 * `Aplicacion/MaquinaEstados/MaquinaEstadosContrato::activar()` para el
 * porqué de cada una.
 */
final class ActivacionContratoNoDisponible extends DomainException
{
    public static function porFaltaDeVentanas(int $contratoId): self
    {
        return new self(
            "El contrato #{$contratoId} no tiene ninguna ventana de aplicación cargada — no puede pasar a 'vigente'.",
        );
    }

    public static function porFechaInicioEnElPasado(int $contratoId, CarbonImmutable $fechaInicio): self
    {
        return new self(
            "El contrato #{$contratoId} tiene fecha de inicio {$fechaInicio->toDateString()}, ya pasada — no puede pasar a 'vigente'.",
        );
    }
}
