<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Forma de dato primitiva de un dron del catálogo operativo (`ope_drones`),
 * para quien necesita listarlo o elegirlo sin importar el modelo Eloquent
 * `Dron` (ADR 0003, regla 2) — hoy, la cuadrilla de `Personal`, que lleva
 * su dron asignado como parte de su equipamiento.
 */
final readonly class DronCatalogo
{
    public function __construct(
        public int $id,
        public string $identificador,
        public ?string $modelo,
    ) {}

    /** Texto para un `<select>` o una celda: "DR-01 — Agras T50". */
    public function etiqueta(): string
    {
        return $this->modelo === null || $this->modelo === '' ? $this->identificador : "{$this->identificador} — {$this->modelo}";
    }
}
