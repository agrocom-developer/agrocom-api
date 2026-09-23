<?php

namespace App\Dominios\Finanzas\Aplicacion\Concerns;

use App\Dominios\Campania\Contratos\LecturaCampania;
use App\Dominios\Finanzas\Dominio\Excepciones\CampaniaNoAbierta;

/**
 * Guarda compartida por alta y edición de Gasto/Combustible (ADR 0015 punto
 * 6): imputar a una campaña `cerrada` se rechaza, sea al crear o al corregir
 * (tarea 134). Vía `Campania\Contratos\LecturaCampania` (ADR 0003 regla 2):
 * `Campania` es de otro módulo.
 */
trait VerificaCampaniaAbierta
{
    /** @throws CampaniaNoAbierta si la campaña elegida no está `abierta`. */
    private function verificarCampaniaAbierta(LecturaCampania $lecturaCampania, ?int $campaniaId): void
    {
        if ($campaniaId === null) {
            return;
        }

        $campania = $lecturaCampania->obtener($campaniaId);

        if ($campania !== null && ! $campania->admiteImputaciones()) {
            throw CampaniaNoAbierta::paraCampania($campania->codigo, $campania->cerrada);
        }
    }
}
