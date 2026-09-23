<?php

namespace App\Dominios\Mantenimiento\Contratos;

/**
 * Cómo está hoy un recurso de campo (dron, vehículo, generador o batería),
 * para quien lo muestra sin importar el modelo Eloquent (ADR 0003, regla 2;
 * tarea 138).
 *
 * `estado` es el valor crudo que ya usan los listados de Mantenimiento
 * (`activo`, `taller`, `activa`, `mantenimiento`…) y `etiqueta` su texto,
 * resuelto acá porque el vocabulario de estados es de este módulo. `tono` es
 * el mismo que pinta el badge del listado: un estado, un color en toda la app.
 *
 * `operativo` responde "¿se puede llevar al campo hoy?" con el mismo criterio
 * que {@see LecturaEquipamiento::estaDisponible()}: solo el estado sano lo es.
 * Un dron no tiene columna de estado — `ope_drones` es de Operaciones —; el
 * suyo se deduce de sus órdenes de mantenimiento: con alguna abierta está en
 * mantenimiento, sin ninguna está operativo.
 */
final readonly class EstadoRecursoPanel
{
    public function __construct(
        public string $tipo,
        public int $id,
        public string $estado,
        public string $etiqueta,
        public string $tono,
        public bool $operativo,
    ) {}
}
