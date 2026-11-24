<?php

namespace App\Dominios\Operaciones\Infraestructura;

use App\Dominios\Operaciones\Contratos\DatosDesempenioPersona;
use App\Dominios\Operaciones\Contratos\DatosIncidenciaDesempenio;
use App\Dominios\Operaciones\Contratos\DatosRechazoDesempenio;
use App\Dominios\Operaciones\Contratos\DatosSesionDesempenio;
use App\Dominios\Operaciones\Contratos\LecturaDesempenioPersona;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Incidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\SesionRechazo;
use App\Dominios\Solicitudes\Contratos\LecturaContextoSolicitudes;
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
 * `SesionRechazo`/`Incidencia`/`Trabajo` son todas de este módulo (consulta
 * directa); `LecturaContextoSolicitudes::porIds()` resuelve terreno y cliente
 * de las solicitudes de los trabajos en una sola consulta.
 *
 * Todo se recalcula desde los registros de origen en cada llamada
 * (invariante 6) — sin totales cacheados. Las sumas de hectáreas las hace el
 * consumidor con `Brick\Math\BigDecimal` sobre estas filas, nunca `SUM()` de
 * SQL (SQLite las pasaría por REAL/float).
 */
final class LecturaDesempenioPersonaEloquent implements LecturaDesempenioPersona
{
    public function __construct(private readonly LecturaContextoSolicitudes $solicitudes) {}

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

        $contexto = $this->solicitudes->porIds(
            $sesiones->pluck('trabajo.solicitud_id')->filter()->map(fn ($id): int => (int) $id)->unique()->values()->all(),
        );

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

        $filasSesiones = [];
        $filasRechazos = [];

        foreach ($sesiones as $sesion) {
            $trabajo = $sesion->trabajo;
            $rol = $sesion->piloto_id === $personaId ? 'piloto' : 'auxiliar';
            $solicitud = $contexto[(int) $trabajo->solicitud_id] ?? null;

            if ($sesion->anulada_en === null) {
                $filasSesiones[] = new DatosSesionDesempenio(
                    sesionId: $sesion->id,
                    fecha: $sesion->inicio->toDateString(),
                    rol: $rol,
                    trabajoId: $trabajo->id,
                    solicitudId: (int) $trabajo->solicitud_id,
                    solicitudEtiqueta: $solicitud === null ? '—' : $solicitud->etiqueta(),
                    clienteId: $solicitud === null ? 0 : $solicitud->clienteId,
                    clienteNombre: $solicitud === null ? '—' : $solicitud->clienteNombre,
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
                solicitudId: (int) $trabajo->solicitud_id,
                solicitudEtiqueta: $solicitud === null ? '—' : $solicitud->etiqueta(),
                clienteId: $solicitud === null ? 0 : $solicitud->clienteId,
                clienteNombre: $solicitud === null ? '—' : $solicitud->clienteNombre,
                hectareasDeclaradas: (string) $sesion->hectareas_declaradas,
                motivo: $rechazo->motivo,
                rechazadoPorPersonaId: $rechazo->rechazado_por,
            );
        }

        return new DatosDesempenioPersona($filasSesiones, $filasRechazos, $incidencias);
    }
}
