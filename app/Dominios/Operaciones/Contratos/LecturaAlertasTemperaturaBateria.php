<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Frontera de lectura de Operaciones hacia otros módulos (ADR 0003, regla
 * 2): `Mantenimiento` (HU-39, tarea 51) necesita saber si una batería del
 * catálogo (`man_baterias`) tuvo alguna recarga con temperatura por encima
 * del umbral, para calcular su alerta de "retirar la batería" — sin
 * importar el modelo Eloquent `Recarga` ni el resto de sus columnas.
 *
 * El cruce es por IGUALDAD DE TEXTO entre `man_baterias.identificador` y
 * `ope_recargas.bateria_saliente_id` (`string` libre, sin FK), NUNCA por
 * id: no existía catálogo de baterías cuando esa columna se creó (HU-13,
 * tarea 23 — su propio docblock lo dice explícitamente), y convertirla en
 * FK real ahora arriesgaría una tabla con filas reales (incluida la demo,
 * que no se borra) ya escritas por el motor de sync con texto libre, sin
 * necesidad para el CA esencial de esta HU.
 */
interface LecturaAlertasTemperaturaBateria
{
    /**
     * `true` si existe al menos una recarga (no borrada) con
     * `alerta_temperatura = true` cuyo `bateria_saliente_id` coincide,
     * como texto exacto, con `$identificador`.
     */
    public function tuvoAlertaDeTemperatura(string $identificador): bool;
}
