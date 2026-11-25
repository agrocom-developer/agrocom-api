<?php

namespace App\Dominios\Solicitudes\Contratos;

/**
 * Forma de dato primitiva de un servicio asignado para el pull de catálogo de
 * la app de campo (ADR 0003, regla 2; ADR 0037): lo que el equipo lleva al lote
 * de una solicitud ya aceptada. Hectáreas como string (invariante 6 de
 * CLAUDE.md). El contacto del cliente se entrega solo porque el servicio ya está adjudicado.
 */
final readonly class ServicioAsignadoCatalogo
{
    /**
     * @param  array{nombre: string, hectareas: string, geometria: ?array<string, mixed>, referencias_acceso: ?string, colindantes_sensibles: ?string, acceso_dificil: bool}  $terreno
     * @param  array{nombre: string, telefono: string|null, cargo?: string|null, empresa?: string|null}|null  $contacto  la persona de contacto; solo viaja con el servicio adjudicado.
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
        public ?string $precioHaPropuesto = null,
        public ?string $precioHaAcordado = null,
        public ?string $hectareasEjecutadas = null,
        public string $porcentajeAvance = '0.00',
        public ?array $contacto = null,
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
            'precio_ha_propuesto' => $this->precioHaPropuesto,
            'precio_ha_acordado' => $this->precioHaAcordado,
            'hectareas_ejecutadas' => $this->hectareasEjecutadas,
            'porcentaje_avance' => $this->porcentajeAvance,
            'contacto' => $this->contacto,
            'updated_at' => $this->updatedAt,
        ];
    }
}
