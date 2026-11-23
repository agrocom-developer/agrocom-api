<?php

namespace App\Dominios\Mantenimiento\Contratos;

/**
 * Frontera de lectura de Mantenimiento hacia `Operaciones` (ADR 0003, regla
 * 2): la ficha de un dron muestra, en su resumen relacionado, su ficha técnica
 * sin importar el modelo `FichaDron`. Hermano de {@see LecturaEquipamiento},
 * que resuelve lo asignable a una cuadrilla.
 *
 * La relación no es una FK: la ficha se correlaciona por el TEXTO del
 * identificador (`man_drones.identificador_dron`).
 */
interface LecturaMantenimientoPorDron
{
    /**
     * `$identificador` es el identificador del dron de `ope_drones`. Un dron
     * sin ficha vuelve con `fichaId` nulo.
     */
    public function deDron(string $identificador): DatosMantenimientoDron;
}
