<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Frontera de lectura de Operaciones hacia otros módulos (ADR 0003, regla
 * 2): `Mantenimiento` (HU-38, tarea 54) necesita saber cuántas horas de
 * vuelo acumuló cada dron de un modelo dado, para calcular la alerta de un
 * plan de mantenimiento preventivo — sin importar el modelo Eloquent
 * `Sesion` ni `Dron`, ni el resto de sus columnas.
 *
 * El cruce es por IGUALDAD DE TEXTO entre `man_planes_mantenimiento.modelo`
 * y `ope_drones.modelo` (`string` libre, sin catálogo cerrado, ver docblock
 * de `2026_09_02_100004_add_modelo_capacidad_a_ope_drones_table.php`), NUNCA
 * por FK — mismo criterio que {@see LecturaAlertasTemperaturaBateria} contra
 * `ope_recargas.bateria_saliente_id`.
 *
 * Las horas de vuelo de un dron NUNCA se persisten (no existe la columna
 * `horas_vuelo` en `ope_drones`, y no se agrega una): son siempre la suma de
 * `fin - inicio` de sus sesiones cerradas, recalculable desde los registros
 * de origen en cualquier momento — mismo espíritu que la invariante 6 de
 * `CLAUDE.md`, aunque esa invariante hable literalmente de plata y
 * hectáreas.
 */
interface LecturaHorasVueloPorModelo
{
    /**
     * Horas de vuelo acumuladas de cada dron (no borrado) cuyo `modelo`
     * coincide, como texto exacto, con $modelo — solo sesiones cerradas
     * (`fin` no nulo). Indexado por `dron_id`. Vacío si ningún dron tiene
     * ese modelo o ninguno tiene sesiones cerradas.
     *
     * @return array<int, float>
     */
    public function horasAcumuladasPorModelo(string $modelo): array;
}
