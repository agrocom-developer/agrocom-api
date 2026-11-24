<?php

namespace App\Dominios\Solicitudes\Contratos\Eventos;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Hecho de dominio (especificación §7): el cliente dio conformidad, venció el
 * plazo sin observación o una disputa se resolvió, y la solicitud quedó
 * `completada` con su monto final y su comisión congelados. Abre la calificación
 * (7 días) y avisa al cliente y al proveedor. DTO de primitivos.
 */
final readonly class SolicitudCompletada implements ShouldDispatchAfterCommit
{
    /**
     * @param  list<int>  $usuariosProveedor  cuentas del proveedor a las que se avisa.
     * @param  string  $origen  `cliente`, `sistema` (conformidad tácita) o `administrador` (disputa).
     */
    public function __construct(
        public int $solicitudId,
        public int $clienteId,
        public int $clienteUsuarioId,
        public ?int $proveedorId,
        public array $usuariosProveedor,
        public string $montoFinal,
        public ?string $comisionPorcentaje,
        public ?string $comisionMonto,
        public string $origen,
    ) {}
}
