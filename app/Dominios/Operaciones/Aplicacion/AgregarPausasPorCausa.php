<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Dominio\CausaPausa;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Pausa;
use Illuminate\Support\Carbon;

/**
 * Caso de uso: agregado de minutos de pausa por causa, filtrable por período
 * (HU-44, tarea 58; CA: "agregado por causa en el tablero" — el "tablero" de
 * esta HU, mismo shape que la maqueta del dashboard, ahora con
 * datos reales).
 *
 * `SUM(duracion_minutos)` directo en SQL, no un `diffInMinutes` por fila en
 * PHP: portable entre SQLite (tests) y Postgres (prod) sin depender de
 * funciones de fecha específicas de cada motor (ver docblock de la
 * migración) — es la razón por la que `duracion_minutos` se guarda en vez de
 * calcularse al leer.
 *
 * Devuelve TODAS las causas del catálogo, incluidas las que no tuvieron
 * ninguna pausa en el período (0 minutos): el tablero necesita el catálogo
 * completo, no solo las causas con datos.
 */
final class AgregarPausasPorCausa
{
    /** @return array{total_minutos: int, por_causa: array<string, int>} */
    public function ejecutar(?string $periodo = null): array
    {
        $periodoValido = $periodo !== null && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodo) === 1;

        $sumasPorCausa = Pausa::query()
            ->when($periodoValido, function ($consulta) use ($periodo) {
                $inicioMes = Carbon::createFromFormat('Y-m-d', "{$periodo}-01")->startOfMonth();
                $finMes = $inicioMes->copy()->endOfMonth();

                $consulta->whereBetween('inicio', [$inicioMes, $finMes]);
            })
            ->selectRaw('causa, SUM(duracion_minutos) as minutos')
            ->groupBy('causa')
            ->pluck('minutos', 'causa');

        $porCausa = [];

        foreach (CausaPausa::cases() as $causa) {
            $porCausa[$causa->value] = (int) ($sumasPorCausa[$causa->value] ?? 0);
        }

        return [
            'total_minutos' => array_sum($porCausa),
            'por_causa' => $porCausa,
        ];
    }
}
