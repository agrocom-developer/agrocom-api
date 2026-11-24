<?php

namespace App\Dominios\Proveedores\Contratos;

/**
 * Escritura de la calificación promedio de un proveedor (especificación §4.3).
 * La calcula el módulo de calificaciones a partir de sus registros; `Proveedores`
 * solo la guarda en su ficha, porque cada módulo escribe únicamente sus tablas
 * (ADR 0003). La política de sanciones de la asignación la lee de la ficha.
 */
interface CalificacionDeProveedores
{
    /**
     * @param  string  $promedio  decimal entre 0 y 5, dos decimales.
     * @param  int  $conteo  calificaciones visibles que entraron al promedio.
     */
    public function actualizar(int $proveedorId, string $promedio, int $conteo): void;
}
