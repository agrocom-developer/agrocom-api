<?php

namespace App\Dominios\Operaciones\Dominio;

use Brick\Math\BigDecimal;

/**
 * Regla de cierre de una orden de aplicación (`vigente → consumida`, ADR 0022,
 * adenda del 19/9/2026): solo se cierra cuando la aplicación se ejecutó entera —
 * hay al menos una orden de trabajo (un trabajo equipo×lote), TODAS las
 * hectáreas de la orden están asignadas a algún equipo y NINGÚN trabajo sigue
 * abierto, es decir, todos los equipos culminaron los suyos. El cierre sigue
 * siendo manual del encargado: esta política solo dice si ya se puede pedir,
 * nada lo dispara solo.
 *
 * Como cada lote no admite más hectáreas asignadas que las solicitadas (lo
 * garantiza `CrearOrdenTrabajo`), comparar los totales equivale a comparar lote
 * por lote. Hectáreas en `DECIMAL` (como `BigDecimal`), nunca `float`
 * (invariante 6).
 *
 * Reglas puras, sin Eloquent (verificado por `tests/Unit/ArquitecturaModulosTest`):
 * las lee quien aplica el cierre (`MaquinaEstadosOrden`, con las cuentas de su
 * transacción) y quien lo ofrece (los pasos y los modales del panel, con las del
 * resumen), para que lo que la pantalla anuncia y lo que el servidor exige
 * nunca se contradigan. Si hay más de un impedimento, devuelve el primero en
 * este orden: sin trabajos, hectáreas sin asignar, trabajos abiertos.
 */
final class PoliticaCierreOrden
{
    /**
     * @param  int  $trabajos  trabajos (equipo×lote) que tiene la orden.
     * @param  int  $abiertos  de esos, los que todavía no se cerraron.
     * @param  string  $hectareasSolicitadas  suma de las hectáreas de los lotes de la orden.
     * @param  string  $hectareasAsignadas  suma de las hectáreas asignadas a equipos en sus trabajos.
     * @return ImpedimentoCierreOrden|null `null` si la orden ya se puede cerrar.
     */
    public static function impedimento(int $trabajos, int $abiertos, string $hectareasSolicitadas, string $hectareasAsignadas): ?ImpedimentoCierreOrden
    {
        return match (true) {
            $trabajos === 0 => ImpedimentoCierreOrden::SinTrabajos,
            self::hectareasSinAsignar($hectareasSolicitadas, $hectareasAsignadas)->isPositive() => ImpedimentoCierreOrden::HectareasSinAsignar,
            $abiertos > 0 => ImpedimentoCierreOrden::TrabajosAbiertos,
            default => null,
        };
    }

    /** Hectáreas de la orden que todavía no tienen equipo (cero si ya están todas; nunca negativo). */
    public static function hectareasSinAsignar(string $hectareasSolicitadas, string $hectareasAsignadas): BigDecimal
    {
        $faltan = BigDecimal::of($hectareasSolicitadas)->minus(BigDecimal::of($hectareasAsignadas));

        return $faltan->isNegative() ? BigDecimal::zero() : $faltan;
    }
}
