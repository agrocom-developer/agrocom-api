<?php

namespace App\Dominios\Notificaciones\Contratos;

/**
 * Un aviso tal como lo pinta la campana del panel: ya con su texto traducido
 * y el ícono resuelto, sin ningún modelo Eloquent (ADR 0003, regla 2).
 * `creadaEn` va en ISO 8601 para que la cáscara pueda ordenarlo junto con las
 * alertas técnicas de `Operaciones`, que es la otra fuente de la campana.
 */
final readonly class NotificacionPanel
{
    public function __construct(
        public int $id,
        public string $icono,
        public string $titulo,
        public string $creadaEn,
        public bool $leida,
    ) {}
}
