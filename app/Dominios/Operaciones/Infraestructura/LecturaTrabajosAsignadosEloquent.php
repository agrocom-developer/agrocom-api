<?php

namespace App\Dominios\Operaciones\Infraestructura;

use App\Dominios\Operaciones\Contratos\LecturaTrabajosAsignados;
use App\Dominios\Operaciones\Contratos\TrabajoAsignadoCatalogo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Implementación Eloquent del contrato de lectura de trabajos asignados
 * (HU-70, tarea 85). Vive fuera de `Infraestructura/Eloquent/` a propósito,
 * mismo criterio que `LecturaOrdenesVigentesEloquent`: esa subcarpeta está
 * reservada a modelos que extienden `ModeloDominio`
 * (`tests/Unit/ArquitecturaModulosTest.php` lo exige), y esta clase no es un
 * modelo — es el adaptador que el `ServiceProvider` del módulo liga a
 * {@see LecturaTrabajosAsignados}.
 */
final class LecturaTrabajosAsignadosEloquent implements LecturaTrabajosAsignados
{
    /**
     * Columna calculada con la "versión" de cada fila para el cursor: la
     * modificación más reciente entre el trabajo y su Orden de Trabajo.
     */
    private const string VERSION = 'catalogo_actualizado_en';

    public function listarModificadosDesde(?string $cursorActualizadoEn, ?int $cursorId, int $limite, array $equipoTrabajoIds): array
    {
        if ($equipoTrabajoIds === []) {
            return [];
        }

        $consulta = Trabajo::query();
        $version = $this->expresionVersion($consulta);
        $id = $consulta->getQuery()->getGrammar()->wrap('ope_trabajos.id');

        return $consulta
            ->leftJoin('ope_ordenes_trabajo as ot', 'ot.id', '=', 'ope_trabajos.orden_trabajo_id')
            ->select('ope_trabajos.*')
            ->selectRaw("{$version} as ".self::VERSION)
            ->whereIn('ope_trabajos.equipo_trabajo_id', $equipoTrabajoIds)
            ->with('ordenTrabajo')
            ->when(
                $cursorActualizadoEn !== null && $cursorId !== null,
                fn (Builder $consulta) => $consulta->where(
                    fn (Builder $consulta) => $consulta
                        ->whereRaw("{$version} > ?", [Carbon::parse($cursorActualizadoEn)])
                        ->orWhere(
                            fn (Builder $consulta) => $consulta
                                ->whereRaw("{$version} = ?", [Carbon::parse($cursorActualizadoEn)])
                                ->where('ope_trabajos.id', '>', $cursorId),
                        ),
                ),
            )
            ->orderByRaw($version)
            ->orderByRaw($id)
            ->limit($limite)
            ->get()
            ->map(fn (Trabajo $trabajo): TrabajoAsignadoCatalogo => $this->aCatalogo($trabajo))
            ->all();
    }

    /**
     * Un trabajo se vuelve a entregar cuando cambia él O su Orden de Trabajo
     * (petición de agrocom-field del 1/10/2026): los límites efectivos viven
     * en la cabecera de la tanda, y editarlos después de asignar no toca
     * `ope_trabajos.updated_at`. Se resuelve en el criterio del cursor y no
     * tocando los trabajos al editar la Orden de Trabajo: esa escritura
     * caería también sobre trabajos ya validados (invariante 2).
     *
     * El `LEFT JOIN` no filtra `deleted_at` de la Orden de Trabajo a
     * propósito: darla de baja también cambia los límites efectivos (pasan a
     * los defaults) y debe volver a bajar el trabajo. `CASE` y no
     * `GREATEST()`: es portable a SQLite (los tests) y no depende de cómo
     * cada motor trata el `NULL` de un trabajo sin Orden de Trabajo.
     *
     * Los defaults del sistema son constantes de código: cambiarlos es un
     * deploy, no una edición, y no re-baja nada por sí solo.
     *
     * @param  Builder<Trabajo>  $consulta
     */
    private function expresionVersion(Builder $consulta): string
    {
        $gramatica = $consulta->getQuery()->getGrammar();
        $delTrabajo = $gramatica->wrap('ope_trabajos.updated_at');
        $deLaOrdenTrabajo = $gramatica->wrap('ot.updated_at');

        return "(CASE WHEN {$deLaOrdenTrabajo} IS NOT NULL AND {$deLaOrdenTrabajo} > {$delTrabajo} "
            ."THEN {$deLaOrdenTrabajo} ELSE {$delTrabajo} END)";
    }

    /**
     * Los 7 límites viajan EFECTIVOS (`Trabajo::limitesEfectivos()`): la app es
     * offline y no puede resolver la herencia Orden de Trabajo → default del
     * sistema por su cuenta. `updated_at` es la versión del cursor
     * ({@see self::expresionVersion()}), no la columna del trabajo: es la
     * posición que el próximo pull tiene que reconocer.
     */
    private function aCatalogo(Trabajo $trabajo): TrabajoAsignadoCatalogo
    {
        $limites = $trabajo->limitesEfectivos();

        return new TrabajoAsignadoCatalogo(
            id: $trabajo->id,
            uuidCliente: $trabajo->uuid_cliente,
            ordenId: $trabajo->orden_id,
            loteId: $trabajo->lote_id,
            hectareasDeclaradas: $trabajo->hectareas_declaradas,
            equipoTrabajoId: (int) $trabajo->equipo_trabajo_id,
            humedadMinPct: $limites->humedadMinPct,
            vientoMaxKmh: $limites->vientoMaxKmh,
            temperaturaMaxC: $limites->temperaturaMaxC,
            humedadMaxPct: $limites->humedadMaxPct,
            alturaVueloM: $limites->alturaVueloM,
            velocidadVueloKmh: $limites->velocidadVueloKmh,
            anchoPasadaM: $limites->anchoPasadaM,
            updatedAt: Carbon::parse((string) $trabajo->getAttribute(self::VERSION))->toIso8601String(),
        );
    }
}
