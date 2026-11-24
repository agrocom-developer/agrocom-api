<?php

namespace App\Dominios\Solicitudes\Dominio;

/** Estado de un cliente (especificación §4.1): un cliente suspendido no puede solicitar servicios. */
enum EstadoCliente: string
{
    case Activo = 'activo';
    case Suspendido = 'suspendido';

    /** Tono del badge del panel (`atoms/badge`). */
    public function tono(): string
    {
        return match ($this) {
            self::Activo => 'success',
            self::Suspendido => 'danger',
        };
    }
}
