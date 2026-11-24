<?php

namespace App\Dominios\Solicitudes\Infraestructura;

use App\Dominios\Solicitudes\Contratos\DatosServicio;
use App\Dominios\Solicitudes\Contratos\LecturaServicios;
use App\Dominios\Solicitudes\Dominio\EstadoSolicitud;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\HistorialSolicitud;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\SolicitudServicio;

/** Lectura de solicitudes para calificaciones, disputas y métricas (ver {@see LecturaServicios}). */
final class LecturaServiciosEloquent implements LecturaServicios
{
    public function datos(int $solicitudId): ?DatosServicio
    {
        $solicitud = SolicitudServicio::query()->with('cliente')->find($solicitudId);

        if ($solicitud === null) {
            return null;
        }

        return new DatosServicio(
            id: $solicitud->id,
            estado: $solicitud->estado->value,
            clienteId: $solicitud->cliente_id,
            clienteUsuarioId: $solicitud->cliente->usuario_id,
            proveedorId: $solicitud->proveedor_id,
            equipoId: $solicitud->equipo_id,
            hectareasSolicitadas: $solicitud->hectareas_solicitadas,
            montoEstimado: $solicitud->monto_estimado,
            montoFinal: $solicitud->monto_final,
            comisionPorcentaje: $solicitud->comision_porcentaje,
            comisionMonto: $solicitud->comision_monto,
            ejecutadaEn: $this->momentoDe($solicitud->id, EstadoSolicitud::Ejecutada),
            completadaEn: $this->momentoDe($solicitud->id, EstadoSolicitud::Completada),
        );
    }

    /** Cuándo entró la solicitud por última vez a ese estado, según su historial. */
    private function momentoDe(int $solicitudId, EstadoSolicitud $estado): ?string
    {
        $paso = HistorialSolicitud::query()
            ->where('solicitud_id', $solicitudId)
            ->where('estado_nuevo', $estado->value)
            ->orderByDesc('id')
            ->first();

        return $paso?->created_at->toIso8601String();
    }
}
