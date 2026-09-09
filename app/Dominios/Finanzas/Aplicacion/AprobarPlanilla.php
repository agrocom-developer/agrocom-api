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
 *
 * `lockForUpdate()` sobre `$planilla` (mismo patrón que `GenerarActaTrabajo`
 * y `FirmarActa`): sin él, dos pedidos de aprobación casi simultáneos (doble
 * clic, dos pestañas del dueño) pueden leer ambos `estado=Borrador`, pasar
 * juntos la guarda de `MaquinaEstadosPlanilla::aprobar()` y terminar los dos
 * escribiendo `Aprobada` — acá no hay `UNIQUE` que convierta la carrera en
 * error (a diferencia de `GenerarPlanilla`), así que el lock es la única
 * defensa; no hace falta el `catch (QueryException)` de esos otros casos
 * porque no hay índice que pueda chocar.
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
            /** @var Planilla $planillaLock */
            $planillaLock = Planilla::query()->whereKey($planilla->id)->lockForUpdate()->firstOrFail();

            $planillaAprobada = $this->maquina->aprobar($planillaLock, $aprobadaPor);

            foreach ($planillaAprobada->detalles as $detalle) {
                $this->generarRecibo->ejecutar($planillaAprobada, $detalle);
            }

            return $planillaAprobada;
        });
    }
}
