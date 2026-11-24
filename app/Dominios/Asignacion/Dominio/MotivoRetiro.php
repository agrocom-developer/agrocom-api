<?php

namespace App\Dominios\Asignacion\Dominio;

/** Por qué una oferta pasó a `retirada` (especificación §5.3). */
enum MotivoRetiro: string
{
    case OtroAcepto = 'otro_acepto';
    case Cancelada = 'cancelada';
    case Reasignada = 'reasignada';
    case AsignacionForzada = 'asignacion_forzada';
}
