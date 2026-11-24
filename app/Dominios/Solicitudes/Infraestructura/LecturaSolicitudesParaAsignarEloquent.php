<?php

namespace App\Dominios\Solicitudes\Infraestructura;

use App\Dominios\Solicitudes\Contratos\DatosSolicitudParaAsignar;
use App\Dominios\Solicitudes\Contratos\LecturaSolicitudesParaAsignar;
use App\Dominios\Solicitudes\Dominio\EstadoSolicitud;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\SolicitudServicio;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Zona;

/** Lectura de solicitudes para el motor de asignación (ver {@see LecturaSolicitudesParaAsignar}). */
final class LecturaSolicitudesParaAsignarEloquent implements LecturaSolicitudesParaAsignar
{
    public function datos(int $solicitudId): ?DatosSolicitudParaAsignar
    {
        $solicitud = SolicitudServicio::query()->with(['terreno', 'cliente'])->find($solicitudId);

        return $solicitud === null ? null : $this->datosDe($solicitud, $this->zonas([$solicitud]));
    }

    public function datosBloqueando(int $solicitudId): ?DatosSolicitudParaAsignar
    {
        $solicitud = SolicitudServicio::query()->lockForUpdate()->find($solicitudId);

        if ($solicitud === null) {
            return null;
        }

        $solicitud->load(['terreno', 'cliente']);

        return $this->datosDe($solicitud, $this->zonas([$solicitud]));
    }

    public function publicadasSinTomar(int $limite): array
    {
        return array_map('intval', SolicitudServicio::query()
            ->where('estado', EstadoSolicitud::Publicada->value)
            ->orderBy('id')
            ->limit($limite)
            ->pluck('id')
            ->all());
    }

    public function colaDeAsignacion(?string $estado, int $limite): array
    {
        $estados = [
            EstadoSolicitud::Publicada->value, EstadoSolicitud::EnAsignacion->value, EstadoSolicitud::SinProveedor->value,
            EstadoSolicitud::Asignada->value, EstadoSolicitud::EnCamino->value,
        ];

        $solicitudes = SolicitudServicio::query()
            ->with(['terreno', 'cliente'])
            ->whereIn('estado', $estado !== null && in_array($estado, $estados, true) ? [$estado] : $estados)
            ->orderByRaw("CASE estado WHEN 'sin_proveedor' THEN 0 WHEN 'en_asignacion' THEN 1 WHEN 'publicada' THEN 2 ELSE 3 END")
            ->orderByDesc('id')
            ->limit($limite)
            ->get();

        $zonas = $this->zonas($solicitudes->all());

        return $solicitudes->map(fn (SolicitudServicio $solicitud): DatosSolicitudParaAsignar => $this->datosDe($solicitud, $zonas))->all();
    }

    public function equiposOcupados(string $ventanaDesde, string $ventanaHasta, ?int $exceptoSolicitudId = null): array
    {
        return array_values(array_unique(array_map('intval', SolicitudServicio::query()
            ->whereNotNull('equipo_id')
            ->whereIn('estado', array_map(fn (EstadoSolicitud $estado): string => $estado->value, EstadoSolicitud::conEquipo()))
            ->whereDate('ventana_desde', '<=', $ventanaHasta)
            ->whereDate('ventana_hasta', '>=', $ventanaDesde)
            ->when($exceptoSolicitudId !== null, fn ($consulta) => $consulta->where('id', '!=', $exceptoSolicitudId))
            ->pluck('equipo_id')
            ->all())));
    }

    /**
     * @param  list<SolicitudServicio>  $solicitudes
     * @return array<int, Zona>
     */
    private function zonas(array $solicitudes): array
    {
        $ids = array_values(array_unique(array_filter(array_map(fn (SolicitudServicio $s): ?int => $s->terreno->zona_id, $solicitudes))));

        return $ids === [] ? [] : Zona::query()->whereIn('id', $ids)->get()->keyBy('id')->all();
    }

    /** @param  array<int, Zona>  $zonas */
    private function datosDe(SolicitudServicio $solicitud, array $zonas): DatosSolicitudParaAsignar
    {
        $terreno = $solicitud->terreno;
        $zona = $terreno->zona_id === null ? null : ($zonas[$terreno->zona_id] ?? null);

        return new DatosSolicitudParaAsignar(
            id: $solicitud->id,
            estado: $solicitud->estado->value,
            clienteId: $solicitud->cliente_id,
            clienteUsuarioId: (int) $solicitud->cliente->usuario_id,
            tipoAplicacion: $solicitud->tipo_aplicacion->value,
            hectareas: $solicitud->hectareas_solicitadas,
            urgente: $solicitud->urgente,
            ventanaDesde: $solicitud->ventana_desde->toDateString(),
            ventanaHasta: $solicitud->ventana_hasta->toDateString(),
            montoEstimado: $solicitud->monto_estimado,
            proveedorId: $solicitud->proveedor_id,
            equipoId: $solicitud->equipo_id,
            terrenoNombre: $terreno->nombre,
            geometriaTerreno: $terreno->geometria,
            zonaId: $terreno->zona_id,
            zonaNombre: $zona?->nombre,
            zonaLat: $zona?->centroide_lat,
            zonaLng: $zona?->centroide_lng,
            creadaEn: $solicitud->created_at?->toIso8601String() ?? '',
        );
    }
}
