<?php

namespace App\Dominios\Comercial\Dominio;

/**
 * Prefijo con el que se numeran los lotes de una propiedad: "Lote " en
 * "Lote 7". La edición en bloque lo necesita para nombrar los lotes que
 * agrega cuando sube la cantidad, y lo propone a partir de los códigos que
 * la propiedad ya tiene — el de uso más frecuente entre los que terminan en
 * número, y "Lote " si ninguno lo hace.
 */
final class PrefijoCodigoLote
{
    public const POR_DEFECTO = 'Lote ';

    /** @param  iterable<string>  $codigos */
    public static function inferir(iterable $codigos): string
    {
        $usos = [];

        foreach ($codigos as $codigo) {
            if (preg_match('/^(.*\D)\d+$/u', $codigo, $partes) === 1) {
                $usos[$partes[1]] = ($usos[$partes[1]] ?? 0) + 1;
            }
        }

        if ($usos === []) {
            return self::POR_DEFECTO;
        }

        arsort($usos);

        return (string) array_key_first($usos);
    }
}
