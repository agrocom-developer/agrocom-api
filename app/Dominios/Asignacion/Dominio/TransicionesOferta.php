<?php

namespace App\Dominios\Asignacion\Dominio;

/**
 * Tabla de transiciones permitidas de la oferta (invariante 7 de CLAUDE.md;
 * especificación §5.3): una oferta viva se acepta, se rechaza, vence o se
 * retira. Una aceptada solo se retira si un administrador reasigna la
 * solicitud. Pura, sin Eloquent ni base.
 */
final class TransicionesOferta
{
    /** @var array<string, list<EstadoOferta>> */
    private const array PERMITIDAS = [
        'ofrecida' => [EstadoOferta::Aceptada, EstadoOferta::Rechazada, EstadoOferta::Vencida, EstadoOferta::Retirada],
        'aceptada' => [EstadoOferta::Retirada],
        'rechazada' => [],
        'vencida' => [],
        'retirada' => [],
    ];

    public static function permitida(EstadoOferta $desde, EstadoOferta $hacia): bool
    {
        return in_array($hacia, self::PERMITIDAS[$desde->value], true);
    }

    /** @return list<EstadoOferta> */
    public static function destinosDesde(EstadoOferta $desde): array
    {
        return self::PERMITIDAS[$desde->value];
    }
}
