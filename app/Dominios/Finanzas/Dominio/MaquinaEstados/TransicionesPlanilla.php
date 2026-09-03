<?php

namespace App\Dominios\Finanzas\Dominio\MaquinaEstados;

use App\Dominios\Finanzas\Dominio\EstadoPlanilla;

/**
 * Tabla de transiciones permitidas para `planilla` (invariante 7 de
 * CLAUDE.md). Reglas puras, sin Eloquent ni `Illuminate\Database` (mismo
 * criterio que `TransicionesActa`, verificado por
 * `tests/Unit/ArquitecturaModulosTest.php`).
 *
 * Un único camino: `borrador → aprobada`, sin vuelta atrás. La generación
 * misma no es una "transición" en el sentido de esta tabla: es la creación
 * del registro con su estado inicial, que hace
 * `Aplicacion/MaquinaEstados/MaquinaEstadosPlanilla::generar()` directamente
 * (mismo criterio que `TransicionesActa`/`generar()`).
 */
final class TransicionesPlanilla
{
    /** @var array<string, list<string>> */
    private const array PERMITIDAS = [
        'borrador' => ['aprobada'],
        'aprobada' => [],
    ];

    public static function permitida(EstadoPlanilla $desde, EstadoPlanilla $hacia): bool
    {
        return in_array($hacia->value, self::PERMITIDAS[$desde->value], true);
    }
}
