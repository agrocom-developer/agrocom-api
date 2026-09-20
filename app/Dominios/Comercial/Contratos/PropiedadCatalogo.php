<?php

namespace App\Dominios\Comercial\Contratos;

/**
 * Forma de dato primitiva de una propiedad del catálogo de `Comercial`
 * (`com_propiedades`), para quien necesita listarla o elegirla sin importar
 * el modelo Eloquent `Propiedad` (ADR 0003, regla 2) — hoy, la pantalla de
 * estadías en hacienda de `Operaciones` (dónde se alojó cada cuadrilla).
 *
 * `clienteNombre` viaja ya resuelto (razón social del cliente dueño): quien
 * consume este DTO no importa el modelo `Cliente` para armar la etiqueta.
 */
final readonly class PropiedadCatalogo
{
    public function __construct(
        public int $id,
        public string $nombre,
        public int $clienteId,
        public string $clienteNombre,
    ) {}

    /** Texto para un `<select>` o una celda: "Santa Cecilia — Agropecuaria del Sur". */
    public function etiqueta(): string
    {
        return "{$this->nombre} — {$this->clienteNombre}";
    }
}
