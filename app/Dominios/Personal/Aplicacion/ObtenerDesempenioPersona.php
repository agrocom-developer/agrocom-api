<?php

namespace App\Dominios\Personal\Aplicacion;

use App\Dominios\Operaciones\Contratos\DatosDesempenioPersona;
use App\Dominios\Operaciones\Contratos\LecturaDesempenioPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;

/**
 * Caso de uso de la ficha de desempeño de una persona (HU-58, tarea 81):
 * "¿qué hizo esta persona esta campaña?", por sesión y no por equipo de
 * trabajo (ADR 0015 punto 3 — la pertenencia a un equipo no es exclusiva).
 *
 * Le pide todo a {@see LecturaDesempenioPersona} (Operaciones, vía su
 * Contratos/) SIN filtrar por cliente — el filtro se aplica acá, en
 * PHP, sobre el resultado completo del rango de fechas, porque las opciones
 * de los selects de cliente salen de esos mismos datos (valores
 * únicos ya presentes), no de una consulta aparte a `Comercial`.
 *
 * Suma hectáreas con `Brick\Math\BigDecimal` (invariante 6 de CLAUDE.md,
 * bcmath no instalado en este entorno) — nunca `SUM()` de SQL, que en SQLite
 * (motor de los tests) pasaría por REAL/float.
 */
final class ObtenerDesempenioPersona
{
    public function __construct(private readonly LecturaDesempenioPersona $lectura) {}

    public function ejecutar(int $personaId, string $desde, string $hasta, ?int $clienteId): ResultadoDesempenioPersona
    {
        $datos = $this->lectura->ejecutar($personaId, $desde, $hasta);

        $clientesDisponibles = $this->opcionesDeFiltro($datos);

        $sesiones = collect($datos->sesiones)
            ->when($clienteId !== null, fn (Collection $filas) => $filas->where('clienteId', $clienteId))
            ->values();

        $rechazosFiltrados = collect($datos->rechazos)
            ->when($clienteId !== null, fn (Collection $filas) => $filas->where('clienteId', $clienteId))
            ->values();

        $nombresPorPersonaId = PerPersona::query()
            ->whereIn('id', $rechazosFiltrados->pluck('rechazadoPorPersonaId')->unique())
            ->pluck('nombre', 'id');

        $rechazos = $rechazosFiltrados
            ->map(fn ($rechazo) => new FilaRechazoDesempenio(
                sesionId: $rechazo->sesionId,
                fecha: $rechazo->fecha,
                rol: $rechazo->rol,
                solicitudEtiqueta: $rechazo->solicitudEtiqueta,
                clienteId: $rechazo->clienteId,
                clienteNombre: $rechazo->clienteNombre,
                hectareasDeclaradas: $rechazo->hectareasDeclaradas,
                motivo: $rechazo->motivo,
                rechazadoPorNombre: $nombresPorPersonaId->get($rechazo->rechazadoPorPersonaId, '—'),
            ))
            ->values();

        // Las incidencias de una sesión rechazada siguen siendo hechos de
        // esa sesión (p. ej. la batería que motivó el rechazo): visibles si
        // la sesión O su rechazo pasaron el filtro de cliente.
        $sesionIdsVisibles = $sesiones->pluck('sesionId')
            ->merge($rechazosFiltrados->pluck('sesionId'))
            ->all();

        $incidencias = collect($datos->incidencias)
            ->whereIn('sesionId', $sesionIdsVisibles)
            ->values();

        $hectareasAplicadas = $sesiones->reduce(
            fn (BigDecimal $acumulado, $sesion) => $acumulado->plus(BigDecimal::of($sesion->hectareasDeclaradas)),
            BigDecimal::zero(),
        );

        $sesionesValidadas = $sesiones->filter(fn ($sesion) => $sesion->estado === 'validado')->count();

        return new ResultadoDesempenioPersona(
            sesiones: $sesiones->all(),
            rechazos: $rechazos->all(),
            incidencias: $incidencias->all(),
            hectareasAplicadas: (string) $hectareasAplicadas->toScale(2),
            totalSesionesValidadas: $sesionesValidadas,
            totalSesionesRechazadas: $rechazos->count(),
            totalIncidencias: $incidencias->count(),
            clientesDisponibles: $clientesDisponibles,
        );
    }

    /**
     * @return array<int, string> clienteId => nombre, ordenado por nombre.
     */
    private function opcionesDeFiltro(DatosDesempenioPersona $datos): array
    {
        $clientes = [];

        foreach ([...$datos->sesiones, ...$datos->rechazos] as $fila) {
            if ($fila->clienteId !== 0) {
                $clientes[$fila->clienteId] = $fila->clienteNombre;
            }
        }

        asort($clientes);

        return $clientes;
    }
}
