<?php

namespace App\Dominios\Personal\Dominio;

/**
 * Solapamiento entre vigencias de una asignación a un equipo de trabajo
 * (tarea 72, HU-49, ADR 0015 punto 3) — mismo problema que
 * `Comercial\Dominio\ValidadorSolapamientoVentanas` (rango contra rango),
 * con fechas en vez de horas y con dos desenlaces distintos según de qué
 * equipos se trate:
 *
 * - Misma persona (o mismo recurso) en el MISMO equipo, vigencias que se
 *   pisan: es la misma fila dos veces — se rechaza.
 * - Misma persona (o mismo recurso) en DOS equipos DISTINTOS, vigencias que
 *   se pisan: es el préstamo real de personal/equipamiento entre cuadrillas
 *   — corrección del dueño del 7/9/2026 ("ese personal puede realizar
 *   diferentes trabajos... porque en caso de que no hubiera personal se
 *   acoplará el que se tiene disponible"). Se avisa, pero se guarda igual.
 *
 * Sirve igual para personas y para recursos: en ambos casos el problema es
 * "esta vigencia nueva, contra las vigencias existentes del mismo sujeto
 * (una persona_id, o un recurso_tipo+recurso_id) en cualquier equipo". Quien
 * llama arma la lista de vigencias existentes filtrando por ese sujeto —
 * este validador no sabe si compara personas o recursos, y no le hace falta
 * saberlo.
 *
 * Las fechas son string `YYYY-MM-DD`: la comparación lexicográfica de dos
 * strings con ese formato de ancho fijo da el mismo resultado que comparar
 * las fechas como tales, igual que `ValidadorSolapamientoVentanas` con
 * `HH:MM`. `hasta === null` significa "vigente" (sin fecha de fin) y se
 * trata como el extremo más lejano posible, nunca como "no aplica".
 */
final class ValidadorSolapamientoVigencias
{
    /**
     * @param  list<array{equipo_trabajo_id: int, desde: string, hasta: ?string}>  $vigenciasExistentes  vigencias ya guardadas del MISMO sujeto (persona o recurso), en cualquier equipo.
     * @param  array{desde: string, hasta: ?string}  $nueva  vigencia que se quiere asignar.
     * @param  int  $equipoTrabajoId  equipo al que se quiere asignar `$nueva`.
     */
    public static function evaluar(array $vigenciasExistentes, array $nueva, int $equipoTrabajoId): ResultadoSolapamientoVigencias
    {
        $equiposEnAviso = [];

        foreach ($vigenciasExistentes as $existente) {
            if (! self::seSolapan($existente, $nueva)) {
                continue;
            }

            if ($existente['equipo_trabajo_id'] === $equipoTrabajoId) {
                return new ResultadoSolapamientoVigencias(rechazada: true, equiposEnAviso: []);
            }

            $equiposEnAviso[] = $existente['equipo_trabajo_id'];
        }

        return new ResultadoSolapamientoVigencias(
            rechazada: false,
            equiposEnAviso: array_values(array_unique($equiposEnAviso)),
        );
    }

    /**
     * Dos vigencias [desde, hasta] se solapan cuando cada una empieza antes
     * o el mismo día en que la otra termina — a diferencia de las ventanas
     * horarias (medio abiertas, `<` estricto), acá los extremos son días
     * completos e inclusive: terminar un equipo el 10 y empezar otro el 10
     * ya es la misma persona en dos equipos ese día.
     *
     * @param  array{desde: string, hasta: ?string}  $a
     * @param  array{desde: string, hasta: ?string}  $b
     */
    private static function seSolapan(array $a, array $b): bool
    {
        return $a['desde'] <= self::fin($b) && $b['desde'] <= self::fin($a);
    }

    /** @param  array{desde: string, hasta: ?string}  $vigencia */
    private static function fin(array $vigencia): string
    {
        return $vigencia['hasta'] ?? '9999-12-31';
    }
}
