<?php

namespace App\Dominios\Compartido\Dominio;

/**
 * Sanea un texto corto que escribe una persona y que la contraparte lee (por ejemplo,
 * la justificación del precio, ADR 0049): sin etiquetas HTML, sin caracteres de control
 * ni saltos de línea, con los espacios colapsados. Vacío equivale a nulo. La app lo
 * muestra como texto plano; esto es una segunda barrera, no la única.
 */
final class TextoLibre
{
    public static function sanear(?string $texto): ?string
    {
        if ($texto === null) {
            return null;
        }

        $limpio = strip_tags($texto);
        $limpio = (string) preg_replace('/[\p{Cc}\p{Cf}\s]+/u', ' ', $limpio);
        $limpio = trim($limpio);

        return $limpio === '' ? null : $limpio;
    }
}
