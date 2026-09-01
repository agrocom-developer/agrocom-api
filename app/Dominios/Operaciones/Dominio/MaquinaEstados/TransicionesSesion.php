<?php

namespace App\Dominios\Operaciones\Dominio\MaquinaEstados;

use App\Dominios\Operaciones\Dominio\EstadoSesion;

/**
 * Tabla de transiciones permitidas para `sesion` (invariante 7 de
 * CLAUDE.md). Reglas puras, sin Eloquent ni `Illuminate\Database`
 * (verificado por `tests/Unit/ArquitecturaModulosTest.php`).
 *
 * Mismo criterio que `TransicionesTrabajo`: único camino `abierto → cerrado`
 * (HU-05), sin vuelta atrás. La apertura la hace
 * `Aplicacion/MaquinaEstados/MaquinaEstadosSesion::abrir()` directamente,
 * como creación del registro — no es una transición entre dos estados
 * existentes.
 */
final class TransicionesSesion
{
    /** @var array<string, list<string>> */
    private const array PERMITIDAS = [
        'abierto' => ['cerrado'],
        'cerrado' => [],
    ];

    public static function permitida(EstadoSesion $desde, EstadoSesion $hacia): bool
    {
        return in_array($hacia->value, self::PERMITIDAS[$desde->value], true);
    }
}
