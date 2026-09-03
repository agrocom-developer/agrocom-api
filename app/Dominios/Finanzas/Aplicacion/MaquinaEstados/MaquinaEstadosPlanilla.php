<?php

namespace App\Dominios\Finanzas\Aplicacion\MaquinaEstados;

use App\Dominios\Finanzas\Dominio\EstadoPlanilla;
use App\Dominios\Finanzas\Dominio\Excepciones\PlanillaNoAprobable;
use App\Dominios\Finanzas\Dominio\MaquinaEstados\TransicionesPlanilla;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Planilla;
use Carbon\CarbonImmutable;

/**
 * Única clase que crea/muta el `estado` de `planilla` (invariante 7 de
 * CLAUDE.md), mismo criterio que `MaquinaEstadosActa`.
 *
 * Las guardas de NEGOCIO (¿ya existe una planilla viva de este período?,
 * ¿hay algo que aprobar?, ¿quién puede aprobar?) no viven acá: esta clase
 * solo aplica la transición o la rechaza contra {@see TransicionesPlanilla}.
 * Esas guardas viven en `Aplicacion/GenerarPlanilla` y
 * `Aplicacion/AprobarPlanilla`, que invocan esta clase recién después de
 * decidir que corresponde — mismo reparto de responsabilidad que
 * `GenerarActaTrabajo` frente a `MaquinaEstadosActa`.
 */
final class MaquinaEstadosPlanilla
{
    /**
     * @param  array<string, mixed>  $atributos  sin `estado`: lo fija esta clase.
     */
    public function generar(array $atributos): Planilla
    {
        return Planilla::create([...$atributos, 'estado' => EstadoPlanilla::Borrador]);
    }

    /**
     * @throws PlanillaNoAprobable si `$planilla` no está en `Borrador`.
     */
    public function aprobar(Planilla $planilla, int $aprobadaPor): Planilla
    {
        $desde = $planilla->estado;
        $hasta = EstadoPlanilla::Aprobada;

        if (! TransicionesPlanilla::permitida($desde, $hasta)) {
            throw PlanillaNoAprobable::porNoEstarEnBorrador($planilla->id, $desde);
        }

        $planilla->estado = $hasta;
        $planilla->aprobada_por = $aprobadaPor;
        $planilla->aprobada_en = CarbonImmutable::now();
        $planilla->save();

        return $planilla;
    }
}
