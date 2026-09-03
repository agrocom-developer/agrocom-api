<?php

namespace App\Dominios\Finanzas\Aplicacion;

use App\Dominios\Finanzas\Aplicacion\MaquinaEstados\MaquinaEstadosPlanilla;
use App\Dominios\Finanzas\Dominio\Excepciones\PlanillaNoAprobable;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Planilla;
use Illuminate\Support\Facades\DB;

/**
 * `POST /panel/planillas/{planilla}/aprobar` (HU-30, tarea 44): transiciona
 * `Borrador → Aprobada` y genera el recibo en PDF de cada detalle. Gateada
 * por `finanzas.planilla.aprobar`, permiso exclusivo del rol `dueno`
 * (`SeguridadSeeder`) — invariante 4 de CLAUDE.md no aplica acá (no hay
 * "quién generó" contra "quién aprueba" a nivel de persona, es simplemente
 * un permiso que ningún otro rol tiene).
 *
 * La guarda de negocio ("¿la planilla está en `Borrador`?") la aplica
 * `MaquinaEstadosPlanilla::aprobar()` contra `TransicionesPlanilla`,
 * lanzando {@see PlanillaNoAprobable} si no corresponde — este caso de uso
 * no la duplica.
 *
 * Todo dentro de una única transacción, mismo criterio que
 * `GenerarActaTrabajo`: si el `Storage::put()` de algún recibo
 * (`GenerarReciboPlanilla`) fallara, la aprobación completa se revierte —
 * nunca una planilla `Aprobada` con recibos a medio generar.
 */
final class AprobarPlanilla
{
    public function __construct(
        private readonly MaquinaEstadosPlanilla $maquina,
        private readonly GenerarReciboPlanilla $generarRecibo,
    ) {}

    /**
     * @throws PlanillaNoAprobable si `$planilla` no está en `Borrador`.
     */
    public function ejecutar(Planilla $planilla, int $aprobadaPor): Planilla
    {
        return DB::transaction(function () use ($planilla, $aprobadaPor): Planilla {
            $planillaAprobada = $this->maquina->aprobar($planilla, $aprobadaPor);

            foreach ($planillaAprobada->detalles as $detalle) {
                $this->generarRecibo->ejecutar($planillaAprobada, $detalle);
            }

            return $planillaAprobada;
        });
    }
}
