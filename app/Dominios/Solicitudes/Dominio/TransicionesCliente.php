<?php

namespace App\Dominios\Solicitudes\Dominio;

/**
 * Tabla de transiciones permitidas del cliente (invariante 7 de CLAUDE.md): un
 * administrador lo suspende y lo reactiva, sin otro camino. Pura, sin Eloquent
 * ni base. La guarda del motivo al suspender la aplica el caso de uso.
 */
final class TransicionesCliente
{
    /** @var array<string, list<EstadoCliente>> */
    private const array PERMITIDAS = [
        'activo' => [EstadoCliente::Suspendido],
        'suspendido' => [EstadoCliente::Activo],
    ];

    public static function permitida(EstadoCliente $desde, EstadoCliente $hacia): bool
    {
        return in_array($hacia, self::PERMITIDAS[$desde->value], true);
    }
}
