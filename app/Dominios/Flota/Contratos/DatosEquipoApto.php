<?php

namespace App\Dominios\Flota\Contratos;

/** Un equipo apto para recibir ofertas hoy (ver {@see LecturaEquipos}). */
final readonly class DatosEquipoApto
{
    public function __construct(
        public int $equipoId,
        public int $proveedorId,
        public int $pilotoId,
        public int $dronId,
        public string $capacidadTanqueL,
        public string $anchoPasadaM,
    ) {}
}
