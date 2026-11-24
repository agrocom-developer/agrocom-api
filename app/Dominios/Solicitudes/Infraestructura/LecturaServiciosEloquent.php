<?php

namespace App\Dominios\Solicitudes\Infraestructura;

use App\Dominios\Solicitudes\Contratos\DatosServicio;
use App\Dominios\Solicitudes\Contratos\LecturaServicios;
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
            clienteNombre: $solicitud->cliente->razon_social,
            proveedorId: $solicitud->proveedor_id,
            equipoId: $solicitud->equipo_id,
            hectareasSolicitadas: $solicitud->hectareas_solicitadas,
            montoEstimado: $solicitud->monto_estimado,
            montoFinal: $solicitud->monto_final,
            precioHaAcordado: $solicitud->precio_ha_acordado,
            hectareasEjecutadas: $solicitud->hectareas_ejecutadas,
            finalizadaEn: $solicitud->finalizada_en?->toIso8601String(),
        );
    }

    /** Cuándo entró la solicitud por última vez a ese estado, según su historial. */
}
