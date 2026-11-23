<?php

namespace App\Dominios\Personal\Contratos;

/**
 * Forma de dato primitiva de un equipo de trabajo, para quien necesita
 * listar o elegir un equipo sin importar el modelo Eloquent `EquipoTrabajo`
 * (ADR 0003, regla 2) — hoy, la estadía, que
 * registra contra un `equipo_trabajo_id` explícito.
 */
final readonly class DatosEquipoTrabajo
{
    public function __construct(
        public int $id,
        public string $codigo,
        public ?string $nombre,
        public int $baseId,
        public string $estado,
        public string $desde,
        public ?string $hasta,
    ) {}
}
