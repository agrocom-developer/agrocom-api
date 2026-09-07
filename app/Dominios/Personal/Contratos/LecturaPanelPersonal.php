<?php

namespace App\Dominios\Personal\Contratos;

/**
 * Frontera de lectura de Personal hacia el panel (ADR 0003, regla 2; tarea
 * 67): resolver NOMBRES a partir de los ids que otros módulos guardan como
 * FK pelada (`ope_sesiones.piloto_id`, `inv_stock.base_id`).
 *
 * Deliberadamente por lotes (`array $ids` → mapa indexado) y no de a uno:
 * el dashboard pinta veinte sesiones y necesita veinte nombres, y una
 * consulta por fila es el N+1 que este contrato existe para evitar.
 */
interface LecturaPanelPersonal
{
    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public function nombresDePersonas(array $ids): array;

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public function nombresDeBases(array $ids): array;
}
