<?php

namespace App\Dominios\Inventario\Contratos;

/**
 * Frontera de lectura de Inventario hacia `Mantenimiento` (ADR 0003, regla
 * 2): la ficha de una orden de mantenimiento cerrada muestra qué repuestos se
 * consumieron al cerrarla y cuánto costaron, sin importar el modelo
 * `MovimientoStock` ni `Repuesto`.
 *
 * Es el reverso de {@see EscrituraConsumoStock}, que ya cruza esta frontera
 * en sentido contrario al cerrar la orden: lo que aquella escribió como
 * salidas de `inv_movimientos` con el id de la orden, esta lo devuelve.
 *
 * Solo lectura y siempre calculado desde el asiento de origen — `Inventario`
 * no cachea ni `Mantenimiento` persiste nada de esto.
 */
interface LecturaConsumosPorOrden
{
    /**
     * Las líneas consumidas por `$ordenMantenimientoId`, en el orden en que
     * se registraron. Vacío si la orden no consumió ninguna (o todavía no se
     * cerró).
     *
     * @return list<DatosConsumoOrden>
     */
    public function deOrden(int $ordenMantenimientoId): array;

    /** Las mismas líneas en números, para el resumen relacionado. */
    public function resumenDeOrden(int $ordenMantenimientoId): DatosConsumosOrden;
}
