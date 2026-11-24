<?php

namespace App\Dominios\Flota\Aplicacion;

use App\Dominios\Flota\Infraestructura\Eloquent\Dron;

/**
 * Suma un ciclo de batería al dron del marketplace cuando se registra una
 * recarga en una sesión de vuelo de su dron operativo (`ope_drones`). Es el
 * único dato de mantenimiento que se conserva (ADR 0029, punto 4; ADR 0035).
 *
 * Un solo `UPDATE` atómico, sin leer y reescribir: corre dentro de la
 * transacción de sincronización de la recarga y no puede ni perder un ciclo
 * por una carrera ni dejar la transacción en estado abortado por una lectura
 * previa. Una recarga de un dron sin enlace (`ope_dron_id` sin `flo_drones`)
 * no hace nada.
 */
final class IncrementarCiclosBateriaDron
{
    public function ejecutar(?int $opeDronId): void
    {
        if ($opeDronId === null) {
            return;
        }

        Dron::query()
            ->where('ope_dron_id', $opeDronId)
            ->increment('ciclos_bateria');
    }
}
