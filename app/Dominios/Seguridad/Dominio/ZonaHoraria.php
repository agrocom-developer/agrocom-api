<?php

namespace App\Dominios\Seguridad\Dominio;

use DateTimeZone;

/**
 * Validación de un identificador de zona horaria IANA (tarea 63). La única
 * fuente de verdad de qué identificador es válido es la base tzdata que trae
 * PHP (`DateTimeZone::listIdentifiers()`) — nunca una lista propia que se
 * desactualiza con cada cambio de horario de verano/invierno de algún país.
 */
final class ZonaHoraria
{
    public static function esValida(string $identificador): bool
    {
        return in_array($identificador, DateTimeZone::listIdentifiers(), true);
    }
}
