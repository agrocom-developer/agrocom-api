<?php

namespace App\Dominios\Compartido\Dominio;

use Stringable;

/**
 * Lo que el usuario escribió en el buscador del header, ya convertido en algo
 * con lo que se puede consultar (HU del buscador global, 9/9/2026).
 *
 * Value object puro: sin Eloquent, sin `request()`. Quién lo consume decide
 * cómo traduce los términos a SQL — hoy {@see BusquedaEloquent}.
 *
 * Las reglas salen del pedido del dueño, textual: *"que no solo busque una
 * sola palabra, si ingresa palabras múltiples como sea el nombre de una
 * estancia buscar todo completo o una palabra ubicada en medio de una
 * oración"*.
 *
 * 1. **Se parte en palabras** y se exigen TODAS (`AND`), en cualquier orden:
 *    "esperanza estancia" y "estancia esperanza" encuentran lo mismo. Es lo
 *    que hace que "buscar todo completo" funcione sin que el usuario tenga
 *    que escribir la razón social exacta.
 * 2. **Cada palabra puede caer en cualquier posición** (`%palabra%`), también
 *    a mitad de otra: "peranza" encuentra "Esperanza". Por eso el motor NO es
 *    full-text de Postgres, que indexa palabras enteras y ahí fallaría.
 * 3. **Un término de una sola letra se descarta**: con `%a%` no hay fila que
 *    no coincida, así que ensucia el resultado en vez de acotarlo. Si TODOS
 *    los términos son de una letra, la búsqueda queda vacía y quien la
 *    consume muestra el estado "escribí algo más".
 *
 * El tope de términos ({@see MAXIMO_TERMINOS}) existe porque cada uno suma un
 * `AND` con un `OR` por columna: sin tope, un pegote de texto largo arma sola
 * una consulta de cientos de condiciones.
 */
final class TerminosBusqueda implements Stringable
{
    /** Con menos de dos caracteres, un `%término%` no discrimina nada. */
    public const LARGO_MINIMO_TERMINO = 2;

    public const MAXIMO_TERMINOS = 8;

    /** @param list<string> $terminos */
    private function __construct(
        public readonly string $consultaOriginal,
        public readonly array $terminos,
    ) {}

    public static function desde(?string $consulta): self
    {
        $consulta = trim((string) $consulta);

        $palabras = preg_split('/\s+/u', $consulta, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $terminos = array_slice(
            array_values(array_filter(
                array_map(
                    static fn (string $palabra): string => mb_strtolower($palabra),
                    $palabras,
                ),
                static fn (string $palabra): bool => mb_strlen($palabra) >= self::LARGO_MINIMO_TERMINO,
            )),
            0,
            self::MAXIMO_TERMINOS,
        );

        return new self($consulta, $terminos);
    }

    /**
     * `true` cuando no quedó ningún término con el que consultar: o no se
     * escribió nada, o todo lo escrito era de una sola letra.
     */
    public function vacia(): bool
    {
        return $this->terminos === [];
    }

    public function __toString(): string
    {
        return $this->consultaOriginal;
    }
}
