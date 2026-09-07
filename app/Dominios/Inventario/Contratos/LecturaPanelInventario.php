<?php

namespace App\Dominios\Inventario\Contratos;

/**
 * Frontera de lectura de Inventario hacia el dashboard (ADR 0003, regla 2;
 * tarea 67). Hermano de {@see LecturaContadoresPanel}, que devuelve solo el
 * número para el badge del menú: esto devuelve las filas que el panel lista.
 */
interface LecturaPanelInventario
{
    /**
     * Repuestos con `cantidad <= stock_minimo`, del más crítico al menos
     * (mismo criterio de alerta que `ListarStock`).
     *
     * @return list<StockPanel>
     */
    public function stockBajoMinimo(int $limite): array;
}
