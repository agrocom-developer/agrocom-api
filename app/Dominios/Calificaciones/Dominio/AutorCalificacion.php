<?php

namespace App\Dominios\Calificaciones\Dominio;

/** Quién escribe la calificación: la escribe un solo actor y nunca se edita (ADR 0031). */
enum AutorCalificacion: string
{
    case Cliente = 'cliente';
    case Proveedor = 'proveedor';

    /** @return list<EtiquetaCalificacion> el catálogo de etiquetas que ese autor puede usar. */
    public function etiquetas(): array
    {
        return $this === self::Cliente ? EtiquetaCalificacion::haciaProveedor() : EtiquetaCalificacion::haciaCliente();
    }
}
