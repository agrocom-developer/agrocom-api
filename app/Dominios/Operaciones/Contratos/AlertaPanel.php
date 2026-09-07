<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Forma primitiva de una alerta por excepción (HU-19) para la campana del
 * header y la franja de avisos del dashboard (ADR 0003, regla 2).
 *
 * Reemplaza a la maqueta de notificaciones: la campana pintaba
 * tres avisos inventados mientras `ope_alertas` ya tenía filas reales.
 */
final readonly class AlertaPanel
{
    public function __construct(
        public int $id,
        public string $tipo,
        public string $mensaje,
        public string $creadaEn,
        public bool $pendiente,
    ) {}
}
