<?php

namespace App\Dominios\Solicitudes\Infraestructura;

use App\Dominios\Solicitudes\Aplicacion\GestionarFotosDeTerreno;
use App\Dominios\Solicitudes\Contratos\DatosSolicitudAbierta;
use App\Dominios\Solicitudes\Contratos\LecturaMercado;
use App\Dominios\Solicitudes\Dominio\EstadoSolicitud;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\SolicitudServicio;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\TerrenoFoto;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lee del mercado solo lo que un piloto puede ver antes de ofertar
 * (`negociacion-contrato.md` §3.4 y §8): lotes, hectáreas, precio propuesto y
 * datos de la solicitud. Ni el nombre, ni el teléfono, ni el documento del
 * cliente salen de acá.
 */
final class LecturaMercadoEloquent implements LecturaMercado
{
    public function abiertas(array $filtros): array
    {
        return $this->consulta()
            ->when(isset($filtros['hectareas_min']), fn (Builder $q) => $q->where('hectareas_solicitadas', '>=', $filtros['hectareas_min']))
            ->when(isset($filtros['hectareas_max']), fn (Builder $q) => $q->where('hectareas_solicitadas', '<=', $filtros['hectareas_max']))
            ->when(isset($filtros['precio_ha_min']), fn (Builder $q) => $q->where('precio_ha_propuesto', '>=', $filtros['precio_ha_min']))
            ->when(isset($filtros['precio_ha_max']), fn (Builder $q) => $q->where('precio_ha_propuesto', '<=', $filtros['precio_ha_max']))
            ->when(! empty($filtros['fecha_hasta']), fn (Builder $q) => $q->where(fn (Builder $f) => $f->whereNull('ventana_desde')->orWhereDate('ventana_desde', '<=', $filtros['fecha_hasta'])))
            ->when(! empty($filtros['cultivo']), fn (Builder $q) => $q->where('cultivo', 'like', '%'.$filtros['cultivo'].'%'))
            ->when(! empty($filtros['tipo_aplicacion']), fn (Builder $q) => $q->where('tipo_aplicacion', $filtros['tipo_aplicacion']))
            ->orderByDesc('id')
            ->get()
            ->map(fn (SolicitudServicio $solicitud): DatosSolicitudAbierta => $this->datos($solicitud, false))
            ->all();
    }

    public function abierta(int $solicitudId): ?DatosSolicitudAbierta
    {
        $solicitud = $this->consulta()->with('terreno.fotos')->find($solicitudId);

        return $solicitud === null ? null : $this->datos($solicitud, true);
    }

    /** @return Builder<SolicitudServicio> */
    private function consulta(): Builder
    {
        return SolicitudServicio::query()
            ->with(['cliente', 'terreno'])
            ->whereIn('estado', [EstadoSolicitud::Abierta->value, EstadoSolicitud::ConOfertas->value])
            ->whereNotNull('precio_ha_propuesto');
    }

    private function datos(SolicitudServicio $solicitud, bool $conTerreno): DatosSolicitudAbierta
    {
        $terreno = $solicitud->terreno;

        return new DatosSolicitudAbierta(
            id: $solicitud->id,
            estado: $solicitud->estado->value,
            clienteUsuarioId: $solicitud->cliente->usuario_id,
            centro: $this->centro($terreno->geometria),
            hectareasSolicitadas: $solicitud->hectareas_solicitadas,
            precioHaPropuesto: (string) $solicitud->precio_ha_propuesto,
            departamentoCodigo: $solicitud->departamento_codigo,
            provinciaCodigo: $solicitud->provincia_codigo,
            cultivo: $solicitud->cultivo,
            tipoAplicacion: $solicitud->tipo_aplicacion->value,
            productoIndicado: $solicitud->producto_indicado,
            aportaProducto: $solicitud->aporta_producto->value,
            ventanaDesde: $solicitud->ventana_desde?->toDateString(),
            ventanaHasta: $solicitud->ventana_hasta?->toDateString(),
            urgente: $solicitud->urgente,
            observaciones: $solicitud->observaciones,
            publicadaEn: $solicitud->publicada_en?->toIso8601String(),
            montoEstimado: $solicitud->monto_estimado,
            justificacionPrecio: $solicitud->justificacion_precio,
            terreno: $conTerreno ? [
                'nombre' => $terreno->nombre,
                'hectareas' => $terreno->hectareas,
                'geometria' => $terreno->geometria,
                'cultivo' => $terreno->cultivo,
                'referencias_acceso' => $terreno->referencias_acceso,
                'colindantes_sensibles' => $terreno->colindantes_sensibles,
                'acceso_dificil' => $terreno->acceso_dificil,
                'fotos' => $terreno->fotos->map(fn (TerrenoFoto $foto): array => [
                    'id' => $foto->id,
                    'descripcion' => $foto->descripcion,
                    ...GestionarFotosDeTerreno::urls($foto->id),
                ])->all(),
            ] : null,
        );
    }

    /**
     * Centroide aproximado (promedio de los vértices del anillo exterior) de un
     * GeoJSON `Polygon`, `Feature` o `FeatureCollection`.
     *
     * @param  array<string, mixed>|null  $geometria
     * @return array{latitud: float, longitud: float}|null
     */
    private function centro(?array $geometria): ?array
    {
        $anillo = $this->anillo($geometria);
        if ($anillo === []) {
            return null;
        }

        $latitudes = [];
        $longitudes = [];
        foreach ($anillo as $punto) {
            if (is_array($punto) && count($punto) >= 2) {
                $longitudes[] = (float) $punto[0];
                $latitudes[] = (float) $punto[1];
            }
        }

        if ($latitudes === []) {
            return null;
        }

        return ['latitud' => array_sum($latitudes) / count($latitudes), 'longitud' => array_sum($longitudes) / count($longitudes)];
    }

    /**
     * @param  array<string, mixed>|null  $geometria
     * @return list<mixed>
     */
    private function anillo(?array $geometria): array
    {
        if ($geometria === null) {
            return [];
        }

        return match ($geometria['type'] ?? null) {
            'Feature' => $this->anillo(is_array($geometria['geometry'] ?? null) ? $geometria['geometry'] : null),
            'FeatureCollection' => $this->anillo(is_array($geometria['features'][0] ?? null) ? $geometria['features'][0] : null),
            'Polygon' => is_array($geometria['coordinates'][0] ?? null) ? $geometria['coordinates'][0] : [],
            'MultiPolygon' => is_array($geometria['coordinates'][0][0] ?? null) ? $geometria['coordinates'][0][0] : [],
            default => [],
        };
    }
}
