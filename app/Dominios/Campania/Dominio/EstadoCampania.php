<?php

namespace App\Dominios\Campania\Dominio;

/**
 * Estados de la campaña (ADR 0015 punto 1; CHECK en `cpn_campanias`). Solo
 * nombra los estados: las transiciones permitidas y sus guardas van en el
 * servicio de dominio de la máquina de estados (invariante 7 de CLAUDE.md),
 * mismo criterio que `EstadoContrato` en Comercial.
 */
enum EstadoCampania: string
{
    case Planificada = 'planificada';
    case Abierta = 'abierta';
    case Cerrada = 'cerrada';
}
