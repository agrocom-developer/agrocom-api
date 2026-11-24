<?php

namespace App\Dominios\Solicitudes\Contratos;

/**
 * Forma de dato primitiva de un servicio asignado para el pull de catálogo de
 * la app de campo (ADR 0003, regla 2; ADR 0037): lo que el equipo lleva al lote
 * de una solicitud ya aceptada. Hectáreas como string (invariante 6 de
 * CLAUDE.md). No lleva datos de contacto del cliente.
 */
final readonly class ServicioAsignadoCatalogo
{
    /**
     * @param  array{nombre: string, hectareas: string, geometria: ?array<string, mixed>, referencias_acceso: ?string, colindantes_sensibles: ?string, acceso_dificil: bool}  $terreno
     */
    public function __construct(
        public int $id,
        public string $uuidCliente,
        public string $estado,
        public int $equipoId,
        public string $tipoAplicacion,
        public string $hectareasSolicitadas,
        public ?string $cultivo,
        public string $productoIndicado,
        public string $aportaProducto,
        public string $ventanaDesde,
        public string $ventanaHasta,
        public bool $urgente,
        public ?string $observaciones,
        public array $terreno,
        public string $updatedAt,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'uuid_cliente' => $this->uuidCliente,
            'estado' => $this->estado,
            'equipo_id' => $this->equipoId,
            'tipo_aplicacion' => $this->tipoAplicacion,
            'hectareas_solicitadas' => $this->hectareasSolicitadas,
            'cultivo' => $this->cultivo,
            'producto_indicado' => $this->productoIndicado,
            'aporta_producto' => $this->aportaProducto,
            'ventana_desde' => $this->ventanaDesde,
            'ventana_hasta' => $this->ventanaHasta,
            'urgente' => $this->urgente,
            'observaciones' => $this->observaciones,
            'terreno' => $this->terreno,
            'updated_at' => $this->updatedAt,
        ];
    }
}
