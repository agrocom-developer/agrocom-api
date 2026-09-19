<?php

namespace App\Dominios\Operaciones\Dominio;

/**
 * Por qué una orden vigente todavía no se puede cerrar (`vigente → consumida`),
 * según {@see PoliticaCierreOrden}. Catálogo cerrado, sin máquina de estados.
 */
enum ImpedimentoCierreOrden: string
{
    /** La orden no tiene ninguna orden de trabajo registrada: no hay nada que dar por cumplido. */
    case SinTrabajos = 'sin_trabajos';

    /** Todavía quedan hectáreas de la orden sin asignar a ningún equipo. */
    case HectareasSinAsignar = 'hectareas_sin_asignar';

    /** Hay equipos que todavía no terminaron sus trabajos. */
    case TrabajosAbiertos = 'trabajos_abiertos';
}
