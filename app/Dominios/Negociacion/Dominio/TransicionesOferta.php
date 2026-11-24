<?php

namespace App\Dominios\Negociacion\Dominio;

/**
 * Tabla de transiciones permitidas de la oferta (invariante 7 de CLAUDE.md;
 * `negociacion-contrato.md` §2). `aceptada` solo sale hacia `cerrada`, cuando el
 * proveedor adjudicado devuelve la solicitud al mercado. Pura: sin Eloquent.
 */
final class TransicionesOferta
{
    /** @var array<string, list<EstadoOferta>> */
    private const array PERMITIDAS = [
        'vigente' => [EstadoOferta::Aceptada, EstadoOferta::Reemplazada, EstadoOferta::Rechazada, EstadoOferta::Retirada, EstadoOferta::Vencida, EstadoOferta::Cerrada],
        'aceptada' => [EstadoOferta::Cerrada],
        'reemplazada' => [],
        'rechazada' => [],
        'retirada' => [],
        'vencida' => [],
        'cerrada' => [],
    ];

    public static function permitida(EstadoOferta $desde, EstadoOferta $hacia): bool
    {
        return in_array($hacia, self::PERMITIDAS[$desde->value], true);
    }
}
