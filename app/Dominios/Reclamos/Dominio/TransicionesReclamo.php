<?php

namespace App\Dominios\Disputas\Dominio;

/**
 * Tabla de transiciones permitidas de la disputa (invariante 7 de CLAUDE.md;
 * especificación §5.5): `abierta` pasa a `en_revision` cuando soporte la toma;
 * `abierta` y `en_revision` pasan a `retirada` por quien la abrió; `en_revision`
 * pasa a `resuelta`. Una `resuelta` vuelve a `en_revision` una sola vez, por la
 * apelación (la guarda de «una sola vez» es de la `ronda`, no de esta tabla).
 * Pura, sin Eloquent ni base.
 */
final class TransicionesDisputa
{
    /** @var array<string, list<EstadoDisputa>> */
    private const array PERMITIDAS = [
        'abierta' => [EstadoDisputa::EnRevision, EstadoDisputa::Retirada],
        'en_revision' => [EstadoDisputa::Resuelta, EstadoDisputa::Retirada],
        'resuelta' => [EstadoDisputa::EnRevision],
        'retirada' => [],
    ];

    public static function permitida(EstadoDisputa $desde, EstadoDisputa $hacia): bool
    {
        return in_array($hacia, self::PERMITIDAS[$desde->value], true);
    }

    /** @return list<EstadoDisputa> */
    public static function destinosDesde(EstadoDisputa $desde): array
    {
        return self::PERMITIDAS[$desde->value];
    }
}
