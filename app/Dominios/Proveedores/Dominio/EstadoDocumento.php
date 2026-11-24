<?php

namespace App\Dominios\Proveedores\Dominio;

/**
 * Estado de un documento (especificación §4.3): `en_revision` hasta que un
 * administrador lo aprueba o rechaza; `vencido` lo marca el proceso diario.
 */
enum EstadoDocumento: string
{
    case EnRevision = 'en_revision';
    case Aprobado = 'aprobado';
    case Rechazado = 'rechazado';
    case Vencido = 'vencido';

    public function tono(): string
    {
        return match ($this) {
            self::EnRevision => 'warning',
            self::Aprobado => 'success',
            self::Rechazado => 'danger',
            self::Vencido => 'danger',
        };
    }
}
