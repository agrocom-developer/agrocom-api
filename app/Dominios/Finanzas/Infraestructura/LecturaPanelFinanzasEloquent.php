<?php

namespace App\Dominios\Finanzas\Infraestructura;

use App\Dominios\Finanzas\Aplicacion\ListarAnticipos;
use App\Dominios\Finanzas\Aplicacion\ListarDevengosPersona;
use App\Dominios\Finanzas\Aplicacion\SumarAnticiposDelPeriodo;
use App\Dominios\Finanzas\Contratos\AnticipoPanel;
use App\Dominios\Finanzas\Contratos\DevengoPanel;
use App\Dominios\Finanzas\Contratos\LecturaPanelFinanzas;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Anticipo;
use App\Dominios\Finanzas\Infraestructura\Eloquent\DevengoPersonal;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;

/**
 * Implementación del contrato de contenido financiero del dashboard (tarea
 * 67).
 *
 * Delega en {@see ListarDevengosPersona} en vez de repetir la consulta: es
 * el mismo número que la persona ve en `/panel/devengos` y en su recibo de
 * planilla, y el total ya viene sumado con `BigDecimal` ahí. Este adaptador
 * solo recorta y traduce a DTO.
 */
final class LecturaPanelFinanzasEloquent implements LecturaPanelFinanzas
{
    public function __construct(
        private readonly ListarDevengosPersona $listarDevengos,
        private readonly ListarAnticipos $listarAnticipos,
        private readonly SumarAnticiposDelPeriodo $sumarAnticipos,
    ) {}

    public function devengosDelMes(int $personaId, int $limite): array
    {
        $resultado = $this->listarDevengos->ejecutar($personaId);

        $devengos = $resultado['devengos']
            ->sortByDesc('fecha')
            ->take($limite)
            ->map(fn (DevengoPersonal $devengo) => new DevengoPanel(
                id: $devengo->id,
                sesionId: $devengo->sesion_id,
                fecha: $devengo->fecha->toDateString(),
                hectareas: (string) $devengo->hectareas,
                tarifaHa: (string) $devengo->tarifa_ha,
                monto: (string) $devengo->monto,
            ))
            ->values()
            ->all();

        return [
            'devengos' => $devengos,
            'total' => $resultado['total'],
            'periodo' => $resultado['periodo'],
        ];
    }

    public function anticiposDelMes(int $personaId, int $limite): array
    {
        $periodo = Carbon::now()->format('Y-m');

        $anticipos = $this->listarAnticipos->ejecutar($personaId, $periodo, $limite)
            ->getCollection()
            ->map(fn (Anticipo $anticipo) => new AnticipoPanel(
                id: $anticipo->id,
                fecha: $anticipo->fecha->toDateString(),
                monto: (string) $anticipo->monto,
                motivo: $anticipo->motivo,
            ))
            ->values()
            ->all();

        // El total sale de `SumarAnticiposDelPeriodo` y no de sumar la página
        // de arriba: el listado está recortado a `$limite`, así que sumarlo
        // daría un saldo falso en cuanto haya un anticipo más que los que
        // entran en la tarjeta.
        $totalAnticipos = $this->sumarAnticipos->ejecutar($personaId, $periodo);
        $devengado = BigDecimal::of($this->listarDevengos->ejecutar($personaId, $periodo)['total']);

        return [
            'anticipos' => $anticipos,
            'total' => (string) $totalAnticipos->toScale(2, RoundingMode::HalfUp),
            'saldo' => (string) $devengado->minus($totalAnticipos)->toScale(2, RoundingMode::HalfUp),
        ];
    }
}
