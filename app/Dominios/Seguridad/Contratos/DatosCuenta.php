<?php

namespace App\Dominios\Seguridad\Contratos;

/** Foto de una cuenta para mostrar en el panel (ver {@see LecturaCuentas}). */
final readonly class DatosCuenta
{
    public function __construct(
        public int $id,
        public string $nombre,
        public string $username,
        public ?string $email,
        public bool $activa,
    ) {}
}
