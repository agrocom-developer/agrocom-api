<?php

namespace App\Dominios\Negociacion\Aplicacion;

use App\Dominios\Flota\Contratos\LecturaEquipos;
use App\Dominios\Negociacion\Dominio\Distancia;
use App\Dominios\Negociacion\Dominio\EstadoOferta;
use App\Dominios\Negociacion\Dominio\Excepciones\NegociacionInvalida;
use App\Dominios\Negociacion\Infraestructura\Eloquent\Oferta;
use App\Dominios\Proveedores\Contratos\DatosProveedor;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Solicitudes\Contratos\DatosSolicitudAbierta;
use App\Dominios\Solicitudes\Contratos\LecturaMercado;

/**
 * El mapa de solicitudes abiertas del piloto (`negociacion-contrato.md` §8): todas las
 * solicitudes `abierta` y `con_ofertas` de Santa Cruz, con filtros del usuario y sin
 * radio fijo de operación ni división por zonas. Solo cuentas de proveedor habilitadas. Nunca devuelve nombre, teléfono,
 * documento ni dirección del cliente (§3.4), ni precios de las ofertas de otros proveedores.
 */
final class MercadoDeSolicitudes
{
    public function __construct(
        private readonly LecturaMercado $mercado,
        private readonly LecturaProveedores $proveedores,
        private readonly LecturaEquipos $equipos,
        private readonly SinAutocontratacion $autocontratacion,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros  hectareas_min/max, precio_ha_min/max, fecha_hasta, cultivo, tipo_aplicacion, bbox, lat, lon, orden, cursor, limite
     * @return array{items: list<array<string, mixed>>, siguiente_cursor: string|null}
     *
     * @throws NegociacionInvalida
     */
    public function listar(int $usuarioId, array $filtros): array
    {
        $proveedor = $this->habilitado($usuarioId);
        $lat = isset($filtros['lat']) ? (float) $filtros['lat'] : null;
        $lon = isset($filtros['lon']) ? (float) $filtros['lon'] : null;

        $filas = $this->filtrar($proveedor, $filtros);

        $items = array_map(fn (array $fila): array => $this->item($fila['solicitud'], $fila['negociacion'], $fila['vigentes'], $lat, $lon), $filas);

        $orden = (string) ($filtros['orden'] ?? 'reciente');
        usort($items, match ($orden) {
            'distancia' => fn (array $a, array $b): int => ((float) ($a['distancia_km'] ?? PHP_INT_MAX)) <=> ((float) ($b['distancia_km'] ?? PHP_INT_MAX)),
            'precio_ha' => fn (array $a, array $b): int => (float) $b['precio_ha_propuesto'] <=> (float) $a['precio_ha_propuesto'],
            'hectareas' => fn (array $a, array $b): int => (float) $b['hectareas_solicitadas'] <=> (float) $a['hectareas_solicitadas'],
            default => fn (array $a, array $b): int => $b['id'] <=> $a['id'],
        });

        $limite = max(1, min((int) ($filtros['limite'] ?? 50), 100));
        $desde = max(0, (int) base64_decode((string) ($filtros['cursor'] ?? ''), true));
        $pagina = array_slice($items, $desde, $limite);
        $siguiente = $desde + $limite < count($items) ? base64_encode((string) ($desde + $limite)) : null;

        return ['items' => $pagina, 'siguiente_cursor' => $siguiente];
    }

    /**
     * El detalle previo a ofertar: lotes (polígonos), estado del terreno y datos del servicio. Sin datos del cliente.
     *
     * @return array<string, mixed>
     *
     * @throws NegociacionInvalida
     */
    public function detalle(int $usuarioId, int $solicitudId): array
    {
        $proveedor = $this->habilitado($usuarioId);
        $solicitud = $this->mercado->abierta($solicitudId) ?? throw NegociacionInvalida::ajeno('solicitud_ajena');

        $negociacion = $this->negociacionDe($proveedor->id, [$solicitudId])[$solicitudId] ?? null;
        $vigentes = $this->vigentesPorSolicitud([$solicitudId])[$solicitudId] ?? 0;

        return [
            ...$this->item($solicitud, $negociacion, $vigentes, null, null),
            'producto_indicado' => $solicitud->productoIndicado,
            'aporta_producto' => $solicitud->aportaProducto,
            'urgente' => $solicitud->urgente,
            'observaciones' => $solicitud->observaciones,
            'terreno' => $solicitud->terreno,
        ];
    }

    /** @throws NegociacionInvalida `proveedor_no_habilitado` (403) */
    private function habilitado(int $usuarioId): DatosProveedor
    {
        $proveedor = $this->proveedores->proveedorDeLaCuenta($usuarioId);

        if ($proveedor === null || ! $proveedor->estaAprobado() || ! $this->equipos->tieneEquipoApto($proveedor->id)) {
            throw NegociacionInvalida::prohibido('proveedor_no_habilitado');
        }

        return $proveedor;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array{solicitud: DatosSolicitudAbierta, negociacion: string|null, vigentes: int}>
     */
    private function filtrar(DatosProveedor $proveedor, array $filtros): array
    {
        $abiertas = $this->mercado->abiertas($this->filtrosDeContenido($filtros));
        $ids = array_map(fn (DatosSolicitudAbierta $s): int => $s->id, $abiertas);

        $negociaciones = $this->negociacionDe($proveedor->id, $ids);
        $vigentes = $this->vigentesPorSolicitud($ids);
        // Ni las solicitudes de su propia cuenta de cliente ni las de otra cuenta de la misma persona (ADR 0046, punto 10).
        $propios = $this->autocontratacion->propios(array_map(fn (DatosSolicitudAbierta $s): int => $s->clienteUsuarioId, $abiertas), $this->autocontratacion->delProveedor($proveedor->id));
        $bbox = $this->bbox($filtros['bbox'] ?? null);

        $filas = [];
        foreach ($abiertas as $solicitud) {
            if (in_array($solicitud->clienteUsuarioId, $propios, true)) {
                continue;
            }

            if ($bbox !== null && ($solicitud->centro === null
                || $solicitud->centro['longitud'] < $bbox[0] || $solicitud->centro['latitud'] < $bbox[1]
                || $solicitud->centro['longitud'] > $bbox[2] || $solicitud->centro['latitud'] > $bbox[3])) {
                continue;
            }

            $filas[] = ['solicitud' => $solicitud, 'negociacion' => $negociaciones[$solicitud->id] ?? null, 'vigentes' => $vigentes[$solicitud->id] ?? 0];
        }

        return $filas;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function filtrosDeContenido(array $filtros): array
    {
        return array_filter([
            'hectareas_min' => $filtros['hectareas_min'] ?? null,
            'hectareas_max' => $filtros['hectareas_max'] ?? null,
            'precio_ha_min' => $filtros['precio_ha_min'] ?? null,
            'precio_ha_max' => $filtros['precio_ha_max'] ?? null,
            'fecha_hasta' => $filtros['fecha_hasta'] ?? null,
            'cultivo' => $filtros['cultivo'] ?? null,
            'tipo_aplicacion' => $filtros['tipo_aplicacion'] ?? null,
        ], fn (mixed $v): bool => $v !== null && $v !== '');
    }

    /** @return array{0: float, 1: float, 2: float, 3: float}|null oeste, sur, este, norte */
    private function bbox(mixed $texto): ?array
    {
        if (! is_string($texto) || $texto === '') {
            return null;
        }

        $partes = array_map('trim', explode(',', $texto));
        if (count($partes) !== 4 || array_filter($partes, 'is_numeric') !== $partes) {
            throw NegociacionInvalida::dato('bbox_invalido', 'bbox');
        }

        return [(float) $partes[0], (float) $partes[1], (float) $partes[2], (float) $partes[3]];
    }

    /**
     * @param  list<int>  $solicitudIds
     * @return array<int, string> estado de la última oferta del proveedor en cada solicitud
     */
    private function negociacionDe(int $proveedorId, array $solicitudIds): array
    {
        if ($solicitudIds === []) {
            return [];
        }

        return Oferta::query()
            ->where('proveedor_id', $proveedorId)
            ->whereIn('solicitud_id', $solicitudIds)
            ->orderBy('id')
            ->get(['solicitud_id', 'estado'])
            ->mapWithKeys(fn (Oferta $o): array => [$o->solicitud_id => $o->estado->value])
            ->all();
    }

    /**
     * @param  list<int>  $solicitudIds
     * @return array<int, int>
     */
    private function vigentesPorSolicitud(array $solicitudIds): array
    {
        if ($solicitudIds === []) {
            return [];
        }

        return Oferta::query()
            ->whereIn('solicitud_id', $solicitudIds)
            ->where('estado', EstadoOferta::Vigente->value)
            ->selectRaw('solicitud_id, count(*) as total')
            ->groupBy('solicitud_id')
            ->pluck('total', 'solicitud_id')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();
    }

    /** @return array<string, mixed> */
    private function item(DatosSolicitudAbierta $s, ?string $negociacion, int $vigentes, ?float $lat, ?float $lon): array
    {
        return [
            'id' => $s->id,
            'centro' => $s->centro,
            'hectareas_solicitadas' => $s->hectareasSolicitadas,
            'precio_ha_propuesto' => $s->precioHaPropuesto,
            'departamento_codigo' => $s->departamentoCodigo,
            'provincia_codigo' => $s->provinciaCodigo,
            'cultivo' => $s->cultivo,
            'tipo_aplicacion' => $s->tipoAplicacion,
            'ventana_desde' => $s->ventanaDesde,
            'ventana_hasta' => $s->ventanaHasta,
            'ofertas_vigentes' => $vigentes,
            'mi_negociacion' => $negociacion,
            'distancia_km' => $lat !== null && $lon !== null && $s->centro !== null ? Distancia::km($lat, $lon, $s->centro['latitud'], $s->centro['longitud']) : null,
            'publicada_en' => $s->publicadaEn,
            'vence_en' => $s->venceEn,
        ];
    }
}
