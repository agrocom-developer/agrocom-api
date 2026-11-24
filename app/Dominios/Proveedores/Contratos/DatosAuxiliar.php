<?php

namespace App\Dominios\Proveedores\Contratos;

/** Foto de un auxiliar para los demás módulos (ver {@see LecturaProveedores}). */
final readonly class DatosAuxiliar
{
    public function __construct(
        public int $id,
        public int $proveedorId,
        public int $usuarioId,
        public string $nombre,
        public string $estado,
    ) {}
}
