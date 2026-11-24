<?php

namespace App\Dominios\Asignacion\Dominio;

/**
 * Causa con que un proveedor cancela un servicio ya aceptado (política de
 * cancelación, ADR 0033). La falla de equipo avisada con más de 12 horas y la
 * emergencia no suman a la tasa de cancelación; cualquier otra sí.
 */
enum CausaCancelacion: string
{
    case Otra = 'otra';
    case FallaDeEquipo = 'falla_de_equipo';
    case Emergencia = 'emergencia';
}
