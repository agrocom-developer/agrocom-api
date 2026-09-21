<?php

namespace App\Dominios\Mantenimiento\Aplicacion;

use App\Dominios\Mantenimiento\Infraestructura\Eloquent\PlanMantenimiento;
use App\Dominios\Operaciones\Contratos\LecturaHorasVueloPorModelo;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Caso de uso: listado de planes de mantenimiento preventivo (HU-38, tarea
 * 54), con la alerta calculada por fila — nunca persistida (no hay tabla de
 * alertas propia, mismo criterio que `ListarBaterias`/`ope_drones`).
 *
 * La alerta se activa si ALGÚN dron del `modelo` del plan (vía
 * {@see LecturaHorasVueloPorModelo}, el contrato de lectura hacia
 * `Operaciones`) tiene horas acumuladas `>= horas_umbral`. Un plan sin
 * ningún dron de su modelo, o cuyos drones no tienen sesiones cerradas,
 * nunca tiene alerta (el contrato devuelve un array vacío en ese caso).
 *
 * El resultado se deja escrito como atributo NO persistido (`alerta`) sobre
 * cada `PlanMantenimiento` del paginador — así la vista no ejecuta ninguna
 * consulta ni calcula ninguna regla de negocio (checklist §8 de
 * `docs/diseno/guia_pantalla_panel.md`), solo lee un dato ya resuelto.
 */
final class ListarPlanesMantenimiento
{
    public function __construct(private readonly LecturaHorasVueloPorModelo $lecturaHorasVuelo) {}

    /**
     * @param  string|null  $q  búsqueda libre sobre modelo y tarea, las dos columnas de texto del plan.
     * @return LengthAwarePaginator<int, PlanMantenimiento>
     */
    public function ejecutar(?string $q = null, int $porPagina = 15): LengthAwarePaginator
    {
        $paginador = PlanMantenimiento::query()
            ->when($q !== null && $q !== '', function ($consulta) use ($q): void {
                $patron = '%'.mb_strtolower((string) $q).'%';

                $consulta->where(function ($grupo) use ($patron): void {
                    $grupo->whereRaw('LOWER(modelo) LIKE ?', [$patron])
                        ->orWhereRaw('LOWER(tarea) LIKE ?', [$patron]);
                });
            })
            ->orderBy('modelo')
            ->orderBy('horas_umbral')
            ->paginate($porPagina)
            ->withQueryString();

        $paginador->getCollection()->each(function (PlanMantenimiento $plan): void {
            $horasPorDron = $this->lecturaHorasVuelo->horasAcumuladasPorModelo($plan->modelo);
            $umbral = (float) $plan->horas_umbral;

            $plan->alerta = collect($horasPorDron)->contains(fn (float $horas): bool => $horas >= $umbral);
        });

        return $paginador;
    }
}
