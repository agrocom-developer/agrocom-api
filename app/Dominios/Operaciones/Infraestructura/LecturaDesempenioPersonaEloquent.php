<?php

namespace App\Dominios\Operaciones\Infraestructura;

use App\Dominios\Comercial\Contratos\LecturaContrato;
use App\Dominios\Comercial\Contratos\LecturaPanelComercial;
use App\Dominios\Operaciones\Contratos\DatosDesempenioPersona;
use App\Dominios\Operaciones\Contratos\DatosIncidenciaDesempenio;
use App\Dominios\Operaciones\Contratos\DatosRechazoDesempenio;
use App\Dominios\Operaciones\Contratos\DatosSesionDesempenio;
use App\Dominios\Operaciones\Contratos\LecturaDesempenioPersona;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Incidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\SesionRechazo;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Implementación Eloquent de {@see LecturaDesempenioPersona} (tarea 81,
 * HU-58). Vive fuera de `Infraestructura/Eloquent/` a propósito, mismo
 * criterio que `LecturaContratoEloquent`: no es un modelo, es el adaptador
 * que el `ServiceProvider` liga al contrato.
 *
 * Compone tres fuentes sin JOIN cross-módulo (ADR 0003, regla 2), mismo
 * patrón que `Operaciones\Aplicacion\ListarReportesTecnicos`: `Sesion`/
 * `SesionRechazo`/`Incidencia`/`Trabajo`/`OrdenAplicacion` son todas de este
 * módulo (consulta directa); `LecturaPanelComercial::lotesPorId()` resuelve
 * lote/campo y `LecturaContrato::obtenerResumen()` resuelve cliente/campaña
 * — la ruta exacta `orden → contrato → campania_id` que pide ADR 0015 punto
 * 1, memoizada por `contrato_id` porque varias sesiones comparten contrato.
 *
 * Todo se recalcula desde los registros de origen en cada llamada
 * (invariante 6) — sin totales cacheados. Las sumas de hectáreas las hace el
 * consumidor con `Brick\Math\BigDecimal` sobre estas filas, nunca `SUM()` de
 * SQL (SQLite las pasaría por REAL/float).
 */
final class LecturaDesempenioPersonaEloquent implements LecturaDesempenioPersona
{
    public function __construct(
        private readonly LecturaPanelComercial $lecturaPanelComercial,
        private readonly LecturaContrato $lecturaContrato,
    ) {}

    public function ejecutar(int $personaId, string $desde, string $hasta): DatosDesempenioPersona
    {
        $sesiones = Sesion::query()
            ->where(fn (Builder $query) => $query->where('piloto_id', $personaId)->orWhere('auxiliar_id', $personaId))
            ->whereBetween('inicio', [
                CarbonImmutable::parse($desde)->startOfDay(),
                CarbonImmutable::parse($hasta)->endOfDay(),
            ])
            ->with('trabajo')
            ->orderBy('inicio')
            ->get();

        if ($sesiones->isEmpty()) {
            return new DatosDesempenioPersona([], [], []);
        }

        $lotesPorId = $this->lecturaPanelComercial->lotesPorId();

        $ordenIds = $sesiones->pluck('trabajo.orden_id')->unique()->values();
        $contratoIdPorOrdenId = OrdenAplicacion::query()->whereIn('id', $ordenIds)->pluck('contrato_id', 'id');

        $rechazosPorSesionId = SesionRechazo::query()
            ->whereIn('anula_a_id', $sesiones->whereNotNull('anulada_en')->pluck('id'))
            ->get()
            ->keyBy('anula_a_id');

        $incidencias = Incidencia::query()
            ->whereIn('sesion_id', $sesiones->pluck('id'))
            ->orderBy('hora')
            ->get()
            ->map(fn (Incidencia $incidencia): DatosIncidenciaDesempenio => new DatosIncidenciaDesempenio(
                incidenciaId: $incidencia->id,
                sesionId: $incidencia->sesion_id,
                fecha: $incidencia->hora->toDateString(),
                tipo: $incidencia->tipo->value,
                descripcion: $incidencia->descripcion,
            ))
            ->all();

        $resumenPorContratoId = [];
        $filasSesiones = [];
        $filasRechazos = [];

        foreach ($sesiones as $sesion) {
            $trabajo = $sesion->trabajo;
            $rol = $sesion->piloto_id === $personaId ? 'piloto' : 'auxiliar';
            $lote = $lotesPorId[$trabajo->lote_id] ?? null;
            $contratoId = $contratoIdPorOrdenId[$trabajo->orden_id] ?? null;

            $resumen = null;

            if ($contratoId !== null) {
                $resumenPorContratoId[$contratoId] ??= $this->lecturaContrato->obtenerResumen($contratoId);
                $resumen = $resumenPorContratoId[$contratoId];
            }

            if ($sesion->anulada_en === null) {
                $filasSesiones[] = new DatosSesionDesempenio(
                    sesionId: $sesion->id,
                    fecha: $sesion->inicio->toDateString(),
                    rol: $rol,
                    trabajoId: $trabajo->id,
                    loteId: $trabajo->lote_id,
                    loteCodigo: $lote === null ? '—' : $lote->codigo,
                    campoNombre: $lote === null ? '—' : $lote->campoNombre,
                    clienteId: $resumen === null ? 0 : $resumen->clienteId,
                    clienteNombre: $resumen === null ? '—' : $resumen->clienteNombre,
                    campaniaId: $resumen?->campaniaId,
                    campaniaCodigo: $resumen?->campaniaCodigo,
                    dronId: $sesion->dron_id,
                    hectareasDeclaradas: (string) $sesion->hectareas_declaradas,
                    estado: $sesion->estado->value,
                );

                continue;
            }

            $rechazo = $rechazosPorSesionId->get($sesion->id);

            if ($rechazo === null) {
                continue;
            }

            $filasRechazos[] = new DatosRechazoDesempenio(
                sesionId: $sesion->id,
                fecha: $sesion->inicio->toDateString(),
                rol: $rol,
                loteId: $trabajo->lote_id,
                loteCodigo: $lote === null ? '—' : $lote->codigo,
                campoNombre: $lote === null ? '—' : $lote->campoNombre,
                clienteId: $resumen === null ? 0 : $resumen->clienteId,
                clienteNombre: $resumen === null ? '—' : $resumen->clienteNombre,
                campaniaId: $resumen?->campaniaId,
                campaniaCodigo: $resumen?->campaniaCodigo,
                hectareasDeclaradas: (string) $sesion->hectareas_declaradas,
                motivo: $rechazo->motivo,
                rechazadoPorPersonaId: $rechazo->rechazado_por,
            );
        }

        return new DatosDesempenioPersona($filasSesiones, $filasRechazos, $incidencias);
    }
}
