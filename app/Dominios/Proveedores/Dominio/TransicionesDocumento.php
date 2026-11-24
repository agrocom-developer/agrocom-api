<?php

namespace App\Dominios\Proveedores\Dominio;

/**
 * Tabla de transiciones permitidas del estado de un documento (invariante 7 de
 * CLAUDE.md; especificación del marketplace §4.3): `en_revision` lo resuelve un
 * administrador (`aprobado` o `rechazado`); un documento aprobado puede
 * `vencer` por el proceso diario; `rechazado` y `vencido` son terminales (se
 * corrigen cargando un documento nuevo, nunca reescribiendo el anterior).
 * Pura, sin Eloquent ni base.
 */
final class TransicionesDocumento
{
    /** @var array<string, list<EstadoDocumento>> */
    private const array PERMITIDAS = [
        'en_revision' => [EstadoDocumento::Aprobado, EstadoDocumento::Rechazado, EstadoDocumento::Vencido],
        'aprobado' => [EstadoDocumento::Vencido],
        'rechazado' => [],
        'vencido' => [],
    ];

    public static function permitida(EstadoDocumento $desde, EstadoDocumento $hacia): bool
    {
        return in_array($hacia, self::PERMITIDAS[$desde->value], true);
    }
}
