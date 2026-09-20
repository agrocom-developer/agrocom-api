<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Frontera de lectura de Operaciones hacia `Mantenimiento` (ADR 0003, regla
 * 2): la ficha de un vehículo muestra, en su resumen relacionado, en cuántas
 * estadías en hacienda se usó, sin importar el modelo `EstadiaHacienda`.
 *
 * `ope_estadias_hacienda.vehiculo_id` es una FK real hacia `man_vehiculos`, así
 * que el contrato recibe el id del vehículo. «En curso» es lo mismo que en el
 * listado de estadías: sin `salida`.
 */
interface LecturaEstadiasPorVehiculo
{
    /** `$vehiculoId` es el id de `man_vehiculos`. Cuenta lo que no se dio de baja; sin estadías, todo en cero. */
    public function deVehiculo(int $vehiculoId): DatosEstadiasVehiculo;
}
