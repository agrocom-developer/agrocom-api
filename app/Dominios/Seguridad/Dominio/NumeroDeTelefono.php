<?php

namespace App\Dominios\Seguridad\Dominio;

/**
 * Teléfono en formato E.164 (ADR 0046, punto 24): `+` y de 8 a 15 dígitos, el primero
 * distinto de cero. Bolivia (`+591`) es el país por defecto: un número nacional de 8
 * dígitos que empieza con 6 o 7 (celular) es de Bolivia. Cualquier otro país se escribe
 * con su prefijo (`+54 9 11 ...` o `0054 ...`).
 *
 * Es la única fuente del formato: el registro, el login por teléfono, el limitador y la
 * unicidad por tipo de cuenta normalizan por acá, así `70012345`, `+591 700-12345` y
 * `00591 70012345` son el mismo teléfono y colisionan. No prueba que el número exista ni
 * que sea de quien lo escribe (ver ADR 0046, punto 29).
 */
final class NumeroDeTelefono
{
    public const PREFIJO_POR_DEFECTO = '591';

    /** @return string|null E.164 (`+59170012345`) o `null` si no es un teléfono reconocible. */
    public static function normalizar(string $crudo): ?string
    {
        $limpio = trim($crudo);

        if ($limpio === '' || preg_match('/[^0-9+\s().\-]/', $limpio) === 1) {
            return null;
        }

        $internacional = str_starts_with($limpio, '+');
        $digitos = preg_replace('/\D/', '', $limpio) ?? '';

        if ($digitos === '') {
            return null;
        }

        if (! $internacional && str_starts_with($digitos, '00')) {
            $internacional = true;
            $digitos = substr($digitos, 2);
        }

        if (! $internacional) {
            // Sin prefijo de país: es de Bolivia. Con el 591 delante pero sin `+`, también.
            if (str_starts_with($digitos, self::PREFIJO_POR_DEFECTO) && strlen($digitos) === 11) {
                $internacional = true;
            } elseif (strlen($digitos) === 8 && in_array($digitos[0], ['6', '7'], true)) {
                $digitos = self::PREFIJO_POR_DEFECTO.$digitos;
            } else {
                return null;
            }
        }

        if (preg_match('/^[1-9]\d{7,14}$/', $digitos) !== 1) {
            return null;
        }

        // Un número de Bolivia tiene 8 dígitos después del 591.
        if (str_starts_with($digitos, self::PREFIJO_POR_DEFECTO) && strlen($digitos) !== 11) {
            return null;
        }

        return '+'.$digitos;
    }

    /** `true` si el texto, tal como llegó, es un teléfono (no un usuario). */
    public static function pareceTelefono(string $crudo): bool
    {
        return self::normalizar($crudo) !== null;
    }
}
