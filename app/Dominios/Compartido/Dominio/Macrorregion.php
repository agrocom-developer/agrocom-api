<?php

namespace App\Dominios\Compartido\Dominio;

/**
 * Las macrorregiones de Santa Cruz en que se agrupan las zonas (especificación
 * del marketplace §4.1): alimentan los filtros del mapa, los avisos por zona y
 * el tablero del panel. Vive en `Compartido` porque la usan `Solicitudes` (las
 * zonas), `Negociacion` (preferencias de aviso) y `Metricas`.
 */
enum Macrorregion: string
{
    case Cercado = 'cercado';
    case NorteIntegrado = 'norte_integrado';
    case Chiquitania = 'chiquitania';
    case Valles = 'valles';
    case Chaco = 'chaco';

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(static fn (self $region): string => $region->value, self::cases());
    }
}
