<?php

namespace App\Dominios\Personal\Dominio\MaquinaEstados;

use App\Dominios\Personal\Dominio\EstadoEquipoTrabajo;

/**
 * Tabla de transiciones permitidas para un equipo de trabajo (invariante 7 de
 * CLAUDE.md; pedido del dueño, 19/9/2026): `activo ⇄ inactivo`, ida y vuelta
 * — una cuadrilla dada de baja se puede reactivar más adelante, a diferencia
 * de una campaña cerrada (que no tiene marcha atrás) o un contrato finalizado
 * (terminal). Reglas puras, sin Eloquent ni `Illuminate\Database` (verificado
 * por `tests/Unit/ArquitecturaModulosTest.php`).
 *
 * Mismo molde que `Campania\Dominio\MaquinaEstados\TransicionesCampania`.
 */
final class TransicionesEquipoTrabajo
{
    /** @var array<string, list<string>> */
    private const array PERMITIDAS = [
        'activo' => ['inactivo'],
        'inactivo' => ['activo'],
    ];

    public static function permitida(EstadoEquipoTrabajo $desde, EstadoEquipoTrabajo $hacia): bool
    {
        return in_array($hacia->value, self::PERMITIDAS[$desde->value], true);
    }
}
