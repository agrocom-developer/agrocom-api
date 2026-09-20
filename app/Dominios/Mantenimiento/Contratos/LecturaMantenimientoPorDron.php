<?php

namespace App\Dominios\Mantenimiento\Contratos;

/**
 * Frontera de lectura de Mantenimiento hacia `Operaciones` (ADR 0003, regla
 * 2): la ficha de un dron muestra, en su resumen relacionado, su ficha de
 * inventario y sus órdenes de mantenimiento sin importar los modelos
 * `FichaDron` ni `OrdenMantenimiento`. Hermano de {@see LecturaEquipamiento},
 * que resuelve lo asignable a una cuadrilla.
 *
 * Ninguna de las dos relaciones es una FK: la ficha se correlaciona por el
 * TEXTO del identificador (`man_drones.identificador_dron`) y la orden por
 * `equipo_tipo = 'dron'` + `equipo_id` (ver el docblock de sus migraciones).
 * Por eso el contrato pide ambos datos del dron y no solo su id.
 */
interface LecturaMantenimientoPorDron
{
    /**
     * `$dronId` es el id de `ope_drones` y `$identificador` su identificador.
     * Cuenta lo que no se dio de baja; un dron sin ficha ni órdenes vuelve con
     * `fichaId` nulo y las cifras en cero.
     */
    public function deDron(int $dronId, string $identificador): DatosMantenimientoDron;
}
