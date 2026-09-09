<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Una incidencia de una sesión de la persona consultada (tarea 81, HU-58),
 * agrupable "por tipo" (catálogo `TipoIncidencia` de `Operaciones\Dominio`,
 * expuesto acá como `string` — ADR 0003 regla 2, el consumidor no importa el
 * enum del módulo ajeno).
 *
 * Sin cliente/campaña propios: el consumidor los resuelve por `sesionId`
 * cruzando contra `sesiones`/`rechazos` de la misma respuesta (evita
 * cuadruplicar la resolución de contrato por cada incidencia).
 */
final readonly class DatosIncidenciaDesempenio
{
    public function __construct(
        public int $incidenciaId,
        public int $sesionId,
        public string $fecha,
        public string $tipo,
        public ?string $descripcion,
    ) {}
}
