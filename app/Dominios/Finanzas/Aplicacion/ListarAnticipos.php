<?php

namespace App\Dominios\Finanzas\Aplicacion;

use App\Dominios\Finanzas\Infraestructura\Eloquent\Anticipo;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Caso de uso: listado de anticipos, filtrable por persona y por período
 * (HU-29, tarea 41). Solo lectura — mismo patrón de paginación que
 * `ListarBases`. `$periodo` con formato inválido se ignora (sin filtrar),
 * mismo criterio permisivo que `ListarDevengosPersona` cuando no hay
 * período: acá, a diferencia de esa pantalla, "sin período" es un filtro
 * legítimo (ver todo el historial), no un fallback al mes actual.
 */
final class ListarAnticipos
{
    /** @return LengthAwarePaginator<int, Anticipo> */
    public function ejecutar(?int $personaId = null, ?string $periodo = null, int $porPagina = 15): LengthAwarePaginator
    {
        $periodoValido = $periodo !== null && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodo) === 1;

        return Anticipo::query()
            ->when($personaId !== null, fn ($consulta) => $consulta->where('persona_id', $personaId))
            ->when($periodoValido, function ($consulta) use ($periodo) {
                $inicioMes = Carbon::createFromFormat('Y-m-d', "{$periodo}-01")->startOfMonth();
                $finMes = $inicioMes->copy()->endOfMonth();

                $consulta->whereBetween('fecha', [$inicioMes->toDateString(), $finMes->toDateString()]);
            })
            ->orderByDesc('fecha')
            ->paginate($porPagina)
            ->withQueryString();
    }
}
