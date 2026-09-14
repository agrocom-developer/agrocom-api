<?php

namespace App\Dominios\Mantenimiento\Contratos;

/**
 * Frontera de lectura de Mantenimiento hacia otros módulos (ADR 0003, regla
 * 2): `Operaciones` (HU-80, tarea 86) necesita saber cuántos ciclos
 * acumulados tiene una batería del catálogo (`man_baterias`) para imprimirlo
 * en el reporte técnico — sin importar el modelo Eloquent `Bateria` ni el
 * resto de sus columnas. Mismo molde que
 * `Operaciones\Contratos\LecturaAlertasTemperaturaBateria`, pero en la
 * dirección inversa: acá el dato lo tiene `Mantenimiento`, y quien necesita
 * leerlo es `Operaciones`.
 *
 * El cruce es por IGUALDAD DE TEXTO entre `man_baterias.identificador` y
 * `ope_recargas.bateria_saliente_id` (`string` libre, sin FK) — mismo
 * criterio y mismo motivo que `LecturaAlertasTemperaturaBateria` contra esa
 * misma columna.
 */
interface LecturaCiclosBateria
{
    /**
     * Ciclos acumulados de la batería cuyo `identificador` coincide, como
     * texto exacto, con `$identificador`. `null` si ninguna batería del
     * catálogo tiene ese identificador — el reporte lo imprime como dato no
     * disponible, nunca como cero.
     */
    public function obtenerCiclosAcumulados(string $identificador): ?int;
}
