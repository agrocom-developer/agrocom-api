<?php

namespace App\Dominios\Comercial\Dominio;

/**
 * Solapamiento entre ventanas horarias de un mismo contrato (HU-23, tarea
 * 34; plan_sprints.md §174, "no permite ventana fuera del rango del
 * contrato"). Regla pura, sin Eloquent — el índice único parcial
 * `com_contrato_ventanas_unicas` solo bloquea duplicados EXACTOS
 * (mismo `hora_inicio`/`hora_fin`), nunca un solape parcial como
 * `06:00–10:00` contra `08:00–12:00`; esa validación vive acá porque
 * Postgres no la puede expresar con un índice.
 *
 * Las horas son string `HH:MM` (o `HH:MM:SS`, ambos zero-padded): la
 * comparación lexicográfica de dos strings con el mismo formato de ancho fijo
 * da el mismo resultado que comparar los tiempos numéricamente, así que no
 * hace falta parsear a `DateTime` para esto.
 */
final class ValidadorSolapamientoVentanas
{
    /**
     * Primer par de ventanas que se solapan entre sí, o `null` si ninguna lo
     * hace. Compara TODO contra TODO (incluidas, en una edición, las
     * ventanas existentes que no cambiaron) — el conjunto que se valida es
     * siempre el que va a quedar persistido, nunca solo lo nuevo.
     *
     * @param  list<array{hora_inicio: string, hora_fin: string}>  $ventanas
     * @return array{0: array{hora_inicio: string, hora_fin: string}, 1: array{hora_inicio: string, hora_fin: string}}|null
     */
    public static function primerSolapamiento(array $ventanas): ?array
    {
        $cantidad = count($ventanas);

        for ($i = 0; $i < $cantidad; $i++) {
            for ($j = $i + 1; $j < $cantidad; $j++) {
                if (self::seSolapan($ventanas[$i], $ventanas[$j])) {
                    return [$ventanas[$i], $ventanas[$j]];
                }
            }
        }

        return null;
    }

    /**
     * Dos intervalos [inicio, fin) se solapan cuando cada uno empieza antes
     * de que el otro termine. Cubre también el caso "idéntica" (que además ya
     * bloquea el índice único) y el de "una contiene a la otra".
     *
     * @param  array{hora_inicio: string, hora_fin: string}  $a
     * @param  array{hora_inicio: string, hora_fin: string}  $b
     */
    private static function seSolapan(array $a, array $b): bool
    {
        return $a['hora_inicio'] < $b['hora_fin'] && $b['hora_inicio'] < $a['hora_fin'];
    }
}
