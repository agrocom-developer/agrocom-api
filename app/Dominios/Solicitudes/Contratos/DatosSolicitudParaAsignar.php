<?php

namespace App\Dominios\Solicitudes\Contratos;

/**
 * Foto de una solicitud para el motor de asignación (ADR 0030). Primitivos
 * puros: la asignación no importa modelos de `Solicitudes`. La ubicación del
 * terreno viaja como su geometría GeoJSON y, de reserva, el centroide de su
 * zona; quien la use decide cuál sirve.
 */
final readonly class DatosSolicitudParaAsignar
{
    /**
     * @param  array<string, mixed>|null  $geometriaTerreno
     */
    public function __construct(
        public int $id,
        public string $estado,
        public int $clienteId,
        public int $clienteUsuarioId,
        public string $tipoAplicacion,
        public string $hectareas,
        public bool $urgente,
        public string $ventanaDesde,
        public string $ventanaHasta,
        public ?string $montoEstimado,
        public ?int $proveedorId,
        public ?int $equipoId,
        public string $terrenoNombre,
        public ?array $geometriaTerreno,
        public ?int $zonaId,
        public ?string $zonaNombre,
        public ?string $zonaLat,
        public ?string $zonaLng,
        public string $creadaEn,
    ) {}
}
