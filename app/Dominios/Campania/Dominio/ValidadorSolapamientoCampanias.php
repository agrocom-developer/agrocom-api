<?php

namespace App\Dominios\Campania\Dominio;

/**
 * Solapamiento de rango de fechas entre campañas `abierta` (ADR 0015 punto
 * 1). Regla pura, sin Eloquent — mismo molde que `ValidadorSolapamientoVentanas`
 * en Comercial: quien llama (`Aplicacion/MaquinaEstados/MaquinaEstadosCampania::abrir()`)
 * trae las fechas de las demás campañas ya `abierta` desde la base, y esta
 * clase solo decide si se cruzan.
 *
 * Las fechas son string `YYYY-MM-DD`: la comparación lexicográfica de dos
 * strings con ese formato de ancho fijo da el mismo resultado que comparar
 * las fechas numéricamente, así que no hace falta parsear a `DateTime`.
 * A diferencia de las ventanas horarias (`[inicio, fin)`, exclusivo), acá el
 * rango es INCLUSIVO en ambos extremos: dos campañas que terminan/empiezan el
 * mismo día sí se solapan.
 */
final class ValidadorSolapamientoCampanias
{
    /**
     * @param  array{fecha_inicio: string, fecha_fin: string}  $candidata
     * @param  list<array{fecha_inicio: string, fecha_fin: string}>  $otras
     */
    public static function seSolapaConAlguna(array $candidata, array $otras): bool
    {
        foreach ($otras as $otra) {
            if (self::seSolapan($candidata, $otra)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{fecha_inicio: string, fecha_fin: string}  $a
     * @param  array{fecha_inicio: string, fecha_fin: string}  $b
     */
    private static function seSolapan(array $a, array $b): bool
    {
        return $a['fecha_inicio'] <= $b['fecha_fin'] && $b['fecha_inicio'] <= $a['fecha_fin'];
    }
}
