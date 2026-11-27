<?php

namespace App\Dominios\Reclamos\Dominio;

/**
 * Tabla de transiciones permitidas del reclamo (invariante 7 de CLAUDE.md;
 * especificación §5.5): `abierta` pasa a `en_revision` cuando soporte la toma;
 * `abierta` y `en_revision` pasan a `retirada` por quien la abrió; `en_revision`
 * pasa a `resuelta`. Una `resuelta` vuelve a `en_revision` una sola vez, por la
 * apelación (la guarda de «una sola vez» es de la `ronda`, no de esta tabla).
 * Pura, sin Eloquent ni base.
 */
final class TransicionesReclamo
{
    /** @var array<string, list<EstadoReclamo>> */
    private const array PERMITIDAS = [
        'abierta' => [EstadoReclamo::EnRevision, EstadoReclamo::Retirada],
        'en_revision' => [EstadoReclamo::Resuelta, EstadoReclamo::Retirada],
        'resuelta' => [EstadoReclamo::EnRevision],
        'retirada' => [],
    ];

    public static function permitida(EstadoReclamo $desde, EstadoReclamo $hacia): bool
    {
        return in_array($hacia, self::PERMITIDAS[$desde->value], true);
    }

    /** @return list<EstadoReclamo> */
    public static function destinosDesde(EstadoReclamo $desde): array
    {
        return self::PERMITIDAS[$desde->value];
    }
}
