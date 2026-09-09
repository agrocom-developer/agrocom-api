<?php

namespace App\Dominios\Seguridad\Dominio\Excepciones;

use App\Dominios\Seguridad\Aplicacion\ActualizarPreferenciaUsuario;
use App\Dominios\Seguridad\Dominio\ZonaHoraria;
use RuntimeException;

/**
 * El identificador de zona horaria no está entre los que reconoce la base
 * IANA de PHP (`DateTimeZone::listIdentifiers()`) — ver
 * {@see ZonaHoraria}. Defensa en profundidad
 * de {@see ActualizarPreferenciaUsuario}:
 * el selector manual ya valida con `Rule::in(...)` antes de llegar acá
 * (`PreferenciasController`), así que en la práctica esto protege a
 * cualquier otro llamador futuro del caso de uso.
 */
final class ZonaHorariaInvalida extends RuntimeException
{
    public static function paraIdentificador(string $identificador): self
    {
        return new self("'{$identificador}' no es un identificador de zona horaria IANA válido.");
    }
}
