<?php

namespace App\Dominios\Mantenimiento\Contratos;

/**
 * Forma de dato primitiva de un recurso del catálogo de Mantenimiento
 * (vehículo, generador o batería), para quien necesita listarlo o elegirlo
 * sin importar su modelo Eloquent (ADR 0003, regla 2) — hoy, la cuadrilla de
 * `Personal` (su equipamiento) y la estadía de `Operaciones` (el vehículo con
 * el que llegó la cuadrilla).
 *
 * `detalle` es lo que ayuda a reconocerlo además del identificador (marca y
 * modelo del vehículo, modelo del generador); `null` cuando el catálogo no
 * tiene nada que agregar (una batería solo tiene su identificador).
 */
final readonly class RecursoCatalogo
{
    public function __construct(
        public int $id,
        public string $identificador,
        public ?string $detalle,
    ) {}

    /** Texto para un `<select>` o una celda: "2345-ABC — Toyota Hilux". */
    public function etiqueta(): string
    {
        return $this->detalle === null ? $this->identificador : "{$this->identificador} — {$this->detalle}";
    }
}
