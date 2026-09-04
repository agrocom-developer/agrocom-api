<?php

namespace App\Dominios\Operaciones\Infraestructura;

use App\Dominios\Operaciones\Contratos\LecturaContadoresPanel;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Pausa;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use Illuminate\Support\Carbon;

/**
 * Implementación Eloquent del contrato de contadores de panel de Operaciones
 * (TE-14, tarea 60). Vive fuera de `Infraestructura/Eloquent/` por el mismo
 * motivo que {@see LecturaOrdenesVigentesEloquent}: no es un modelo, es el
 * adaptador que el `ServiceProvider` liga al contrato.
 */
final class LecturaContadoresPanelEloquent implements LecturaContadoresPanel
{
    public function ordenesVigentes(): int
    {
        return OrdenAplicacion::query()
            ->where('estado', EstadoOrdenAplicacion::Vigente)
            ->count();
    }

    public function sesionesPendientesValidacion(): int
    {
        return Sesion::query()
            ->where('estado', EstadoSesion::Cerrado)
            ->whereNull('anulada_en')
            ->count();
    }

    public function pausasDelMes(): array
    {
        $inicioMes = Carbon::now()->startOfMonth();
        $finMes = Carbon::now()->endOfMonth();

        $consulta = Pausa::query()->whereBetween('inicio', [$inicioMes, $finMes]);

        return [
            'cantidad' => (clone $consulta)->count(),
            'totalMinutos' => (int) ((clone $consulta)->sum('duracion_minutos')),
        ];
    }
}
