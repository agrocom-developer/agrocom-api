<?php

namespace App\Dominios\Finanzas\Aplicacion;

use App\Dominios\Finanzas\Aplicacion\MaquinaEstados\MaquinaEstadosPlanilla;
use App\Dominios\Finanzas\Infraestructura\Eloquent\DevengoPersonal;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Planilla;
use Brick\Math\BigDecimal;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * `POST /panel/planillas` (HU-30, tarea 44): genera la planilla en
 * `Borrador` de un período (`YYYY-MM`) a partir de los devengos y anticipos
 * de ese mes calendario. Idempotente por REGLA DE NEGOCIO ("una planilla por
 * mes calendario" — `fin_planillas.periodo` es `UNIQUE` entre vivas), mismo
 * criterio que `GenerarActaTrabajo` con `trabajo_id`: un segundo pedido del
 * mismo período devuelve la fila existente sin tocarla.
 *
 * A diferencia de `GenerarActaTrabajo` (que serializa dos pedidos
 * concurrentes con `lockForUpdate()` sobre el `$trabajo` que YA EXISTE), acá
 * no hay una fila padre previa que lockear: la planilla del período todavía
 * no existe en el primer pedido. El `lockForUpdate()` sobre la propia
 * consulta de `Planilla` cubre el caso común (reintento contra una planilla
 * que ya existe); el `catch (QueryException)` de abajo cubre la carrera real
 * del primer pedido concurrente, que solo puede resolverse en el `UNIQUE` de
 * `periodo` — mismo patrón de doble defensa que el catch de
 * `GenerarActaTrabajo` para su propio caso de conflicto de `uuid_cliente`.
 *
 * Para cada persona con al menos un `fin_devengos_personal` en el período:
 * `devengado` sale de `ListarDevengosPersona` (ya suma con `BigDecimal`, no
 * se reimplementa), `anticipos` de `SumarAnticiposDelPeriodo` (extraída de
 * `CalcularDisponibleAnticipo` en esta misma tarea). `neto = devengado -
 * anticipos`, todo con `Brick\Math\BigDecimal` (invariante 6). El `total` de
 * la planilla es la suma de los `neto` de sus detalles — la misma cifra que
 * `sum(fin_devengos_personal.monto) - sum(fin_anticipos.monto)` del período,
 * a centavo exacto (verificado en `PlanillaPanelTest`).
 */
final class GenerarPlanilla
{
    public function __construct(
        private readonly MaquinaEstadosPlanilla $maquina,
        private readonly ListarDevengosPersona $listarDevengos,
        private readonly SumarAnticiposDelPeriodo $sumarAnticipos,
    ) {}

    public function ejecutar(string $periodo): Planilla
    {
        try {
            return DB::transaction(fn (): Planilla => $this->generarBajoLock($periodo));
        } catch (QueryException $excepcion) {
            $existente = Planilla::query()->where('periodo', $periodo)->first();

            if ($existente !== null) {
                return $existente;
            }

            throw $excepcion;
        }
    }

    private function generarBajoLock(string $periodo): Planilla
    {
        $existente = Planilla::query()->where('periodo', $periodo)->lockForUpdate()->first();

        if ($existente !== null) {
            return $existente;
        }

        $inicioMes = Carbon::createFromFormat('Y-m-d', "{$periodo}-01")->startOfMonth();
        $finMes = $inicioMes->copy()->endOfMonth();

        $personaIds = DevengoPersonal::query()
            ->pagables()
            ->whereBetween('fecha', [$inicioMes->toDateString(), $finMes->toDateString()])
            ->distinct()
            ->pluck('persona_id');

        $detalles = [];
        $total = BigDecimal::of('0.00');

        foreach ($personaIds as $personaId) {
            $personaId = (int) $personaId;

            $devengado = BigDecimal::of($this->listarDevengos->ejecutar($personaId, $periodo)['total']);
            $anticipos = $this->sumarAnticipos->ejecutar($personaId, $periodo);
            $neto = $devengado->minus($anticipos);

            $detalles[] = [
                'persona_id' => $personaId,
                'devengado' => (string) $devengado,
                'anticipos' => (string) $anticipos,
                'neto' => (string) $neto,
            ];

            $total = $total->plus($neto);
        }

        $planilla = $this->maquina->generar([
            'periodo' => $periodo,
            'total' => (string) $total,
        ]);

        foreach ($detalles as $detalle) {
            $planilla->detalles()->create($detalle);
        }

        return $planilla;
    }
}
