<?php

namespace App\Dominios\Finanzas\Dominio\MaquinaEstados;

use App\Dominios\Finanzas\Dominio\EstadoRendicion;

/**
 * Tabla de transiciones permitidas para `rendicion` (invariante 7 de
 * CLAUDE.md; espec §4.4, HU-34, tarea 48). Reglas puras, sin Eloquent ni
 * `Illuminate\Database` (mismo criterio que `TransicionesPlanilla`,
 * verificado por `tests/Unit/ArquitecturaModulosTest.php`).
 *
 * Un único camino: `abierta → presentada → aprobada`, sin vuelta atrás. La
 * creación misma no es una "transición" en el sentido de esta tabla: es el
 * alta del registro con su estado inicial, que hace
 * `Aplicacion/MaquinaEstados/MaquinaEstadosRendicion::generar()` directamente
 * (mismo criterio que `TransicionesPlanilla`/`generar()`).
 */
final class TransicionesRendicion
{
    /** @var array<string, list<string>> */
    private const array PERMITIDAS = [
        'abierta' => ['presentada'],
        'presentada' => ['aprobada'],
        'aprobada' => [],
    ];

    public static function permitida(EstadoRendicion $desde, EstadoRendicion $hacia): bool
    {
        return in_array($hacia->value, self::PERMITIDAS[$desde->value], true);
    }
}
