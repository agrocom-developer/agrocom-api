<?php

namespace App\Dominios\Comercial\Dominio;

/**
 * Orden NATURAL de los códigos de lote: L1, L2, … L10 — no L1, L10, L11, L2,
 * que es lo que da ordenar el texto. Es el mismo criterio que
 * `Lote::scopeOrdenadosPorCodigo()` aplica en SQL, para cuando los lotes ya
 * están en memoria (la tabla de lotes del contrato, 21/9/2026): primero la
 * parte de letras que va delante del número (sin distinguir mayúsculas),
 * después el PRIMER número del código como número, y lo que empata, por el
 * código entero. Un código sin número queda antes que los numerados de su
 * mismo prefijo.
 */
final class OrdenCodigoLote
{
    /**
     * Para `usort()`: negativo si `$a` va antes que `$b`.
     */
    public static function comparar(string $a, string $b): int
    {
        [$prefijoA, $numeroA] = self::partes($a);
        [$prefijoB, $numeroB] = self::partes($b);

        return [$prefijoA, $numeroA, $a] <=> [$prefijoB, $numeroB, $b];
    }

    /**
     * @return array{0: string, 1: int}
     */
    private static function partes(string $codigo): array
    {
        preg_match('/^([^0-9]*)([0-9]*)/u', $codigo, $coincidencias);

        return [
            mb_strtolower(trim($coincidencias[1] ?? '')),
            (int) ($coincidencias[2] ?? 0),
        ];
    }
}
