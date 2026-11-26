<?php

namespace App\Dominios\Solicitudes\Contratos;

/**
 * Una solicitud del mercado tal como la ve un piloto antes de ofertar. Sin datos
 * privados del cliente: `clienteUsuarioId` es solo para que la negociación evite
 * que un proveedor oferte sobre su propia cuenta de cliente y nunca se serializa.
 */
final readonly class DatosSolicitudAbierta
{
    /**
     * @param  array{latitud: float, longitud: float}|null  $centro
     * @param  array<string, mixed>|null  $terreno  nombre, hectáreas, geometría, referencias de acceso, colindantes, acceso difícil y fotos con URL firmada de corta vida.
     */
    public function __construct(
        public int $id,
        public string $estado,
        public int $clienteUsuarioId,
        public ?array $centro,
        public string $hectareasSolicitadas,
        public string $precioHaPropuesto,
        public ?int $zonaId,
        public ?string $zona,
        public ?string $macrorregion,
        public ?string $cultivo,
        public string $tipoAplicacion,
        public string $productoIndicado,
        public string $aportaProducto,
        public ?string $ventanaDesde,
        public ?string $ventanaHasta,
        public bool $urgente,
        public ?string $observaciones,
        public ?string $publicadaEn,
        public ?array $terreno,
    ) {}
}
