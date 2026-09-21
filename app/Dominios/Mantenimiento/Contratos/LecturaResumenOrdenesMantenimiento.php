<?php

namespace App\Dominios\Mantenimiento\Contratos;

/**
 * Frontera de lectura de Mantenimiento hacia `Inventario` (ADR 0003, regla
 * 2): la ficha de un repuesto muestra, en su resumen relacionado, en cuántas
 * órdenes de mantenimiento se consumió sin importar el modelo
 * `OrdenMantenimiento`.
 *
 * Los ids de las órdenes los aporta quien ya los tiene: `Inventario` los
 * escribió como `inv_movimientos.orden_mantenimiento_id` al cerrar cada orden
 * (ver `EscrituraConsumoStock`). Acá solo se cuentan y se describen; ninguna
 * orden se modifica.
 */
interface LecturaResumenOrdenesMantenimiento
{
    /**
     * Lo que se sabe de las órdenes con esos ids. Cuenta lo que no se dio de
     * baja: un id de una orden que ya no existe no suma. Sin ids, todo en cero
     * y sin consultar.
     *
     * @param  list<int>  $ordenIds  ids de `man_ordenes_mantenimiento`.
     */
    public function deIds(array $ordenIds): DatosResumenOrdenesMantenimiento;
}
