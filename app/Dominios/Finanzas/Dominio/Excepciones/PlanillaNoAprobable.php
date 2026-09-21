<?php

namespace App\Dominios\Finanzas\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use App\Dominios\Finanzas\Dominio\EstadoPlanilla;
use App\Dominios\Finanzas\Dominio\MaquinaEstados\TransicionesPlanilla;
use DomainException;

/**
 * La planilla referenciada por `POST /panel/planillas/{planilla}/aprobar` no
 * está en `Borrador` (HU-30, tarea 44) — la guarda de negocio de
 * `Aplicacion/AprobarPlanilla`, que se apoya en {@see TransicionesPlanilla}
 * para decidirlo, mismo reparto que `TrabajoNoListoParaActa` frente a
 * `TransicionesActa`.
 */
final class PlanillaNoAprobable extends DomainException
{
    public static function porNoEstarEnBorrador(int $planillaId, EstadoPlanilla $estadoActual): self
    {
        return new self(
            Texto::de('finanzas.errores.planilla_no_aprobable', [
                'planilla_id' => $planillaId,
                'estado_actual' => $estadoActual->value,
            ]),
        );
    }
}
