<?php

namespace App\Dominios\Asignacion\Dominio;

/** Lo que el motor le aplica a un proveedor (política de calificación y de cancelación, ADR 0038). */
enum TipoSancion: string
{
    case Advertencia = 'advertencia';
    case RestriccionOfertas = 'restriccion_ofertas';
    case RevisionSuspension = 'revision_suspension';

    /** Si deja al proveedor sin recibir ofertas mientras está vigente. */
    public function impideOfertas(): bool
    {
        return $this !== self::Advertencia;
    }
}
