<?php

namespace App\Dominios\Asignacion\Dominio;

/** A quién se ofrece en una ronda: a proveedores propios de Agrocom o a terceros e independientes (ADR 0030). */
enum GrupoOferta: string
{
    case Propio = 'propio';
    case Tercero = 'tercero';

    /** El grupo al que pertenece un proveedor según su tipo (`propio`, `independiente` o `tercero`). */
    public static function deTipoDeProveedor(string $tipo): self
    {
        return $tipo === 'propio' ? self::Propio : self::Tercero;
    }
}
