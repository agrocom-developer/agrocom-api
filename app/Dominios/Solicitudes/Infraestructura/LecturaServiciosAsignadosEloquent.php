<?php

namespace App\Dominios\Solicitudes\Infraestructura;

use App\Dominios\Solicitudes\Contratos\LecturaServiciosAsignados;
use App\Dominios\Solicitudes\Contratos\ServicioAsignadoCatalogo;
use App\Dominios\Solicitudes\Contratos\ServicioRetiradoCatalogo;
use App\Dominios\Solicitudes\Dominio\EstadoSolicitud;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\SolicitudServicio;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/** Implementación Eloquent de {@see LecturaServiciosAsignados}. */
final class LecturaServiciosAsignadosEloquent implements LecturaServiciosAsignados
{
    public function asignadosDesde(?string $cursorActualizadoEn, ?int $cursorId, int $limite, array $equipoIds): array
    {
        if ($equipoIds === []) {
            return [];
        }

        return $this->consulta($cursorActualizadoEn, $cursorId, $equipoIds)
            ->whereIn('estado', array_map(fn (EstadoSolicitud $estado): string => $estado->value, EstadoSolicitud::conEquipo()))
            ->with(['terreno', 'cliente'])
            ->limit($limite)
            ->get()
            ->map(fn (SolicitudServicio $solicitud): ServicioAsignadoCatalogo => new ServicioAsignadoCatalogo(
                id: $solicitud->id,
                uuidCliente: $solicitud->uuid_cliente,
                estado: $solicitud->estado->value,
                equipoId: (int) $solicitud->equipo_id,
                tipoAplicacion: $solicitud->tipo_aplicacion->value,
                hectareasSolicitadas: $solicitud->hectareas_solicitadas,
                cultivo: $solicitud->cultivo,
                productoIndicado: $solicitud->producto_indicado,
                aportaProducto: $solicitud->aporta_producto->value,
                ventanaDesde: $solicitud->ventana_desde->toDateString(),
                ventanaHasta: $solicitud->ventana_hasta->toDateString(),
                urgente: $solicitud->urgente,
                observaciones: $solicitud->observaciones,
                terreno: [
                    'nombre' => $solicitud->terreno->nombre,
                    'hectareas' => $solicitud->terreno->hectareas,
                    'geometria' => $solicitud->terreno->geometria,
                    'referencias_acceso' => $solicitud->terreno->referencias_acceso,
                    'colindantes_sensibles' => $solicitud->terreno->colindantes_sensibles,
                    'acceso_dificil' => $solicitud->terreno->acceso_dificil,
                ],
                updatedAt: Carbon::parse($solicitud->updated_at)->toIso8601String(),
                precioHaPropuesto: $solicitud->precio_ha_propuesto,
                precioHaAcordado: $solicitud->precio_ha_acordado,
                hectareasEjecutadas: $solicitud->hectareas_ejecutadas,
                porcentajeAvance: $solicitud->porcentaje_avance,
                // El contacto del cliente se entrega solo con el servicio adjudicado (§3.4).
                contacto: ['nombre' => $solicitud->cliente->razon_social, 'telefono' => $solicitud->cliente->telefono],
            ))
            ->all();
    }

    public function retiradosDesde(?string $cursorActualizadoEn, ?int $cursorId, int $limite, array $equipoIds): array
    {
        if ($equipoIds === []) {
            return [];
        }

        return $this->consulta($cursorActualizadoEn, $cursorId, $equipoIds)
            ->whereNotIn('estado', [
                ...array_map(fn (EstadoSolicitud $estado): string => $estado->value, EstadoSolicitud::conEquipo()),
                EstadoSolicitud::Borrador->value,
            ])
            ->limit($limite)
            ->get()
            ->map(fn (SolicitudServicio $solicitud): ServicioRetiradoCatalogo => new ServicioRetiradoCatalogo(
                id: $solicitud->id,
                uuidCliente: $solicitud->uuid_cliente,
                motivo: match ($solicitud->estado) {
                    EstadoSolicitud::Cancelada => 'cancelada',
                    EstadoSolicitud::Finalizada, EstadoSolicitud::Calificada, EstadoSolicitud::Disputada => 'ejecutada',
                    default => 'reasignada',
                },
                updatedAt: Carbon::parse($solicitud->updated_at)->toIso8601String(),
            ))
            ->all();
    }

    /**
     * @param  list<int>  $equipoIds
     * @return Builder<SolicitudServicio>
     */
    private function consulta(?string $cursorActualizadoEn, ?int $cursorId, array $equipoIds): Builder
    {
        return SolicitudServicio::query()
            ->whereIn('equipo_id', $equipoIds)
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
            ->orderBy('id');
    }
}
