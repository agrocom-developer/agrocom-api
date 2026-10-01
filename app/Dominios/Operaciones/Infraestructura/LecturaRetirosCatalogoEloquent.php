<?php

namespace App\Dominios\Operaciones\Infraestructura;

use App\Dominios\Operaciones\Contratos\LecturaRetirosCatalogo;
use App\Dominios\Operaciones\Contratos\OrdenRetiradaCatalogo;
use App\Dominios\Operaciones\Contratos\TrabajoRetiradoCatalogo;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Dominio\MotivoRetiroTrabajo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\PostgresConnection;
use Illuminate\Database\Query\Builder as Consulta;
use Illuminate\Support\Carbon;

/**
 * Implementación Eloquent de {@see LecturaRetirosCatalogo} (opción B de la
 * propuesta de #312). Fuera de `Infraestructura/Eloquent/` por el mismo
 * motivo que `LecturaTrabajosAsignadosEloquent`: no es un modelo.
 *
 * Órdenes y trabajos solo se dan de baja LÓGICAMENTE (ADR 0007:
 * `EliminarOrden`, `EliminarTrabajo`; `ModeloDominio` bloquea el borrado
 * físico), y el soft delete actualiza `updated_at`. Por eso no hace falta
 * una tabla de tombstones: la propia fila, leída con `withTrashed()`, es el
 * registro que el cursor encuentra.
 */
final class LecturaRetirosCatalogoEloquent implements LecturaRetirosCatalogo
{
    /** Columna calculada con la versión de cada trabajo para el cursor. */
    private const string VERSION = 'catalogo_actualizado_en';

    /** Estado de la orden del trabajo, leído en el mismo `SELECT`. */
    private const string ESTADO_ORDEN = 'catalogo_estado_orden';

    public function ordenesRetiradasDesde(?string $cursorActualizadoEn, ?int $cursorId, int $limite): array
    {
        return OrdenAplicacion::query()
            ->whereIn('estado', [
                EstadoOrdenAplicacion::Pausada->value,
                ...EstadoOrdenAplicacion::valoresCerrados(),
            ])
            ->when(
                $cursorActualizadoEn !== null && $cursorId !== null,
                fn (Builder $consulta) => $consulta->where(
                    fn (Builder $consulta) => $consulta
                        ->where('updated_at', '>', Carbon::parse($cursorActualizadoEn))
                        ->orWhere(
                            fn (Builder $consulta) => $consulta
                                ->where('updated_at', '=', Carbon::parse($cursorActualizadoEn))
                                ->where('id', '>', $cursorId),
                        ),
                ),
            )
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit($limite)
            ->get()
            ->map(fn (OrdenAplicacion $orden): OrdenRetiradaCatalogo => new OrdenRetiradaCatalogo(
                id: $orden->id,
                estado: $orden->estado->value,
                updatedAt: $orden->updated_at->toIso8601String(),
            ))
            ->all();
    }

    public function trabajosRetiradosDesde(
        ?string $cursorActualizadoEn,
        ?int $cursorId,
        int $limite,
        array $equiposVigentes,
        array $equiposHistoricos,
    ): array {
        if ($equiposVigentes === [] && $equiposHistoricos === []) {
            return [];
        }

        $consulta = Trabajo::withTrashed();
        $gramatica = $consulta->getQuery()->getGrammar();
        $version = $this->expresionVersion($gramatica->wrap('ope_trabajos.updated_at'), $gramatica->wrap('oa.updated_at'));
        $estadosCerrados = EstadoOrdenAplicacion::valoresCerrados();

        return $consulta
            ->leftJoin('ope_ordenes_aplicacion as oa', 'oa.id', '=', 'ope_trabajos.orden_id')
            ->select('ope_trabajos.*')
            ->selectRaw("{$version} as ".self::VERSION)
            ->selectRaw($gramatica->wrap('oa.estado').' as '.self::ESTADO_ORDEN)
            // Lo tuvo: es de un equipo suyo hoy, o estuvo asignado a uno de
            // sus equipos alguna vez. Nunca el de un equipo ajeno: su
            // `uuid_cliente` alcanza para abrir sesiones sobre él.
            ->where(fn (Builder $consulta) => $consulta
                ->whereIn('ope_trabajos.equipo_trabajo_id', $equiposVigentes)
                ->when(
                    $equiposHistoricos !== [],
                    fn (Builder $consulta) => $consulta->orWhereExists(
                        fn (Consulta $bitacora) => $this->asignadoAlgunaVezA($bitacora, $equiposHistoricos),
                    ),
                ))
            // Y ya no lo debe tener (ver `MotivoRetiroTrabajo`).
            ->where(fn (Builder $consulta) => $consulta
                ->whereNotNull('ope_trabajos.deleted_at')
                ->orWhereNull('ope_trabajos.equipo_trabajo_id')
                ->orWhereNotIn('ope_trabajos.equipo_trabajo_id', $equiposVigentes)
                ->orWhere('ope_trabajos.estado', EstadoTrabajo::Cerrado->value)
                ->orWhereIn('oa.estado', $estadosCerrados))
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
            ->orderByRaw($gramatica->wrap('ope_trabajos.id'))
            ->limit($limite)
            ->get()
            ->map(fn (Trabajo $trabajo): TrabajoRetiradoCatalogo => new TrabajoRetiradoCatalogo(
                id: $trabajo->id,
                uuidCliente: $trabajo->uuid_cliente,
                motivo: $this->motivo($trabajo, $equiposVigentes, $estadosCerrados)->value,
                updatedAt: Carbon::parse((string) $trabajo->getAttribute(self::VERSION))->toIso8601String(),
            ))
            ->all();
    }

    /**
     * El primero que aplica, en el orden de {@see MotivoRetiroTrabajo}.
     *
     * @param  list<int>  $equiposVigentes
     * @param  list<string>  $estadosCerrados
     */
    private function motivo(Trabajo $trabajo, array $equiposVigentes, array $estadosCerrados): MotivoRetiroTrabajo
    {
        if ($trabajo->trashed()) {
            return MotivoRetiroTrabajo::DadoDeBaja;
        }

        if ($trabajo->equipo_trabajo_id === null || ! in_array((int) $trabajo->equipo_trabajo_id, $equiposVigentes, true)) {
            return MotivoRetiroTrabajo::Reasignado;
        }

        if ($trabajo->estado === EstadoTrabajo::Cerrado) {
            return MotivoRetiroTrabajo::Cerrado;
        }

        // La consulta solo deja pasar filas con algún motivo: si no es
        // ninguno de los anteriores, es la orden cerrada.
        assert(in_array($trabajo->getAttribute(self::ESTADO_ORDEN), $estadosCerrados, true));

        return MotivoRetiroTrabajo::OrdenCerrada;
    }

    /**
     * `EXISTS` sobre la bitácora de auditoría (invariante 9, plataforma):
     * alguna fila de `ope_trabajos` para ESTE trabajo cuyo `despues` dejó
     * `equipo_trabajo_id` en uno de `$equipos`. El observer de la bitácora
     * guarda todos los atributos al crear y las columnas que cambian al
     * actualizar, así que cada asignación de equipo queda registrada.
     *
     * Trabajos anteriores a la bitácora o insertados por fuera de Eloquent
     * no tienen esas filas: para ellos solo vale el criterio de "equipo
     * vigente hoy". Es la única lectura del JSON que difiere por motor
     * (`->>` en Postgres, `json_extract` en SQLite), por eso se arma acá a
     * mano; el `CAST` evita comparar texto con entero en cualquiera de los
     * dos.
     *
     * @param  list<int>  $equipos
     */
    private function asignadoAlgunaVezA(Consulta $bitacora, array $equipos): void
    {
        $gramatica = $bitacora->getGrammar();
        $despues = $gramatica->wrap('plt_bitacoras.despues');
        $equipoDespues = $bitacora->getConnection() instanceof PostgresConnection
            ? "CAST({$despues}->>'equipo_trabajo_id' AS BIGINT)"
            : "CAST(json_extract({$despues}, '$.equipo_trabajo_id') AS INTEGER)";
        $marcadores = implode(', ', array_fill(0, count($equipos), '?'));

        $bitacora->selectRaw('1')
            ->from('plt_bitacoras')
            ->where('plt_bitacoras.tabla', 'ope_trabajos')
            ->whereColumn('plt_bitacoras.registro_id', 'ope_trabajos.id')
            ->whereRaw("{$equipoDespues} in ({$marcadores})", $equipos);
    }

    /**
     * La modificación más reciente entre el trabajo y su orden de aplicación:
     * cerrar o cancelar la orden retira sus trabajos abiertos sin tocar
     * `ope_trabajos` (invariante 2). `CASE` y no `GREATEST()`, mismo criterio
     * que `LecturaTrabajosAsignadosEloquent::expresionVersion()`.
     */
    private function expresionVersion(string $delTrabajo, string $deLaOrden): string
    {
        return "(CASE WHEN {$deLaOrden} IS NOT NULL AND {$deLaOrden} > {$delTrabajo} "
            ."THEN {$deLaOrden} ELSE {$delTrabajo} END)";
    }
}
