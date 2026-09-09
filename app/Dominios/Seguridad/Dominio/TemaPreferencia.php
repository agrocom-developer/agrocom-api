<?php

namespace App\Dominios\Seguridad\Dominio;

/**
 * Tema de color del panel, preferencia persistida por usuario
 * (`sec_user_preferencia.tema`, ADR 0002 punto 4; ADR 0011, extensión
 * 27/8/2026, punto 8). El valor gobierna qué set de tokens CSS aplica el
 * panel — ningún color hardcodeado, invariante 11 de `CLAUDE.md` — pero esa
 * traducción a tokens es responsabilidad del panel/frontend, no de este
 * enum: acá solo se modela el valor persistido.
 */
enum TemaPreferencia: string
{
    case Claro = 'claro';
    case Oscuro = 'oscuro';

    /**
     * Valor que espera `data-bs-theme` (Bootstrap 5.3 color modes). El enum
     * persiste vocabulario en español (`claro`/`oscuro`, ADR 0013); el
     * atributo del DOM habla el idioma de Bootstrap (`light`/`dark`) — este
     * par de mapeos es el único puente entre ambos, nunca un ternario suelto
     * en una vista o un controlador.
     */
    public function atributoBootstrap(): string
    {
        return match ($this) {
            self::Claro => 'light',
            self::Oscuro => 'dark',
        };
    }

    public static function desdeAtributoBootstrap(string $atributo): self
    {
        return $atributo === 'dark' ? self::Oscuro : self::Claro;
    }
}
