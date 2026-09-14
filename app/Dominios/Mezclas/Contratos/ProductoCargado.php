<?php

namespace App\Dominios\Mezclas\Contratos;

/**
 * Forma de dato primitiva de un producto cargado en el caldo, para que
 * `Operaciones\Aplicacion\ArmarContenidoReporteTecnico` liste lo que el
 * piloto transcribió (espec §7, HU-78, tarea 94) sin conocer el modelo
 * Eloquent de `Mezclas` (ADR 0003, regla 2). Nunca incluye dosis, orden de
 * incorporación ni compatibilidad — eso sigue fuera de alcance (§7.1).
 */
final readonly class ProductoCargado
{
    public function __construct(
        public string $producto,
        public string $cantidad,
        public string $unidad,
    ) {}

    /** @return array{producto: string, cantidad: string, unidad: string} */
    public function toArray(): array
    {
        return [
            'producto' => $this->producto,
            'cantidad' => $this->cantidad,
            'unidad' => $this->unidad,
        ];
    }
}
