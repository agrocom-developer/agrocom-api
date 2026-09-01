<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Resultado de aplicar un registro del lote de `POST /api/sync` (espec §2.1,
 * punto 3): los tres únicos casos posibles, `aplicado` / `duplicado` /
 * `rechazado {motivo}`. `$estado` es primitivo (no un enum) porque cruza la
 * frontera del módulo (ADR 0003, regla 2) — mismo criterio que
 * `OrdenAplicacionCatalogo::$estado`.
 *
 * El constructor privado impide construir un estado fuera de los tres casos
 * contemplados; solo los factory estáticos producen instancias válidas.
 */
final readonly class ResultadoSincronizacion
{
    private function __construct(
        public string $estado,
        public ?string $motivo = null,
    ) {}

    public static function aplicado(): self
    {
        return new self('aplicado');
    }

    public static function duplicado(): self
    {
        return new self('duplicado');
    }

    public static function rechazado(string $motivo): self
    {
        return new self('rechazado', $motivo);
    }
}
