<?php

namespace App\Dominios\Proveedores\Contratos;

/** Foto de un piloto para los demás módulos (ver {@see LecturaProveedores}). */
final readonly class DatosPiloto
{
    public function __construct(
        public int $id,
        public int $proveedorId,
        public int $usuarioId,
        public string $nombre,
        public string $estado,
        public string $disponibilidad = 'disponible',
    ) {}
}
