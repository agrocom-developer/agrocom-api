<?php

namespace App\Dominios\Proveedores\Contratos;

/** Foto de un proveedor para los demás módulos (ver {@see LecturaProveedores}). */
final readonly class DatosProveedor
{
    public function __construct(
        public int $id,
        public ?int $usuarioTitularId,
        public string $tipo,
        public string $razonSocial,
        public string $estado,
    ) {}

    public function estaAprobado(): bool
    {
        return $this->estado === 'aprobado';
    }

    /** Un proveedor admite altas de personal y flota mientras espera aprobación o está aprobado. */
    public function admiteAltas(): bool
    {
        return in_array($this->estado, ['pendiente', 'aprobado'], true);
    }
}
