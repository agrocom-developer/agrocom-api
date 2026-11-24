<?php

namespace App\Dominios\Solicitudes\Infraestructura;

use App\Dominios\Solicitudes\Contratos\DatosContextoSolicitud;
use App\Dominios\Solicitudes\Contratos\LecturaContextoSolicitudes;
use App\Dominios\Solicitudes\Dominio\EstadoSolicitud;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\SolicitudServicio;

/** Implementación Eloquent de {@see LecturaContextoSolicitudes}. */
final class LecturaContextoSolicitudesEloquent implements LecturaContextoSolicitudes
{
    public function porIds(array $ids): array
    {
        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            return [];
        }

        $contexto = [];

        SolicitudServicio::query()
            ->with(['terreno:id,nombre', 'cliente:id,razon_social'])
            ->whereIn('id', $ids)
            ->get()
            ->each(function (SolicitudServicio $solicitud) use (&$contexto): void {
                $contexto[$solicitud->id] = new DatosContextoSolicitud(
                    id: $solicitud->id,
                    terrenoNombre: $solicitud->terreno->nombre,
                    cultivo: $solicitud->cultivo,
                    tipoAplicacion: $solicitud->tipo_aplicacion->value,
                    hectareasSolicitadas: $solicitud->hectareas_solicitadas,
                    estado: $solicitud->estado->value,
                    clienteId: $solicitud->cliente_id,
                    clienteNombre: $solicitud->cliente->razon_social,
                    proveedorId: $solicitud->proveedor_id,
                );
            });

        return $contexto;
    }

    public function conteoPorEstado(): array
    {
        $conteo = [];

        foreach (SolicitudServicio::query()->selectRaw('estado, count(*) as total')->groupBy('estado')->get() as $fila) {
            $conteo[$fila->estado->value] = (int) $fila->getAttribute('total');
        }

        $ordenado = [];

        foreach (EstadoSolicitud::cases() as $estado) {
            if (isset($conteo[$estado->value])) {
                $ordenado[$estado->value] = $conteo[$estado->value];
            }
        }

        return $ordenado;
    }
}
