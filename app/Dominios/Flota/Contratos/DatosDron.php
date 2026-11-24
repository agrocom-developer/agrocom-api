<?php

namespace App\Dominios\Flota\Contratos;

/** Foto de un dron del marketplace para los demás módulos (ver {@see LecturaDrones}). */
final readonly class DatosDron
{
    public function __construct(
        public int $id,
        public int $duenoProveedorId,
        public int $operadorProveedorId,
        public string $marca,
        public string $modelo,
        public string $numeroSerie,
        public string $estado,
        public ?int $anio = null,
        public string $capacidadTanqueL = '0.00',
    ) {}

    /** Cómo se nombra el dron en un aviso: «DJI Agras T40 (serie)». */
    public function etiqueta(): string
    {
        return trim("{$this->marca} {$this->modelo}")." ({$this->numeroSerie})";
    }
}
