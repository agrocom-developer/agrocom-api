<?php

namespace App\Dominios\Compartido\Dominio;

/**
 * Tamaño en que se pide un archivo multimedia. El original es el archivo tal como se subió y es lo único
 * que se conserva como respaldo; la miniatura y el tamaño medio son derivados reducidos para pintar listas
 * y cuadrículas sin descargar el original. Sin indicar nada se entrega el original (compatibilidad con las
 * apps ya publicadas).
 */
enum VarianteDeMedio: string
{
    case Miniatura = 'miniatura';
    case Media = 'media';
    case Original = 'original';

    public static function desde(?string $valor): self
    {
        return self::tryFrom((string) $valor) ?? self::Original;
    }

    /** Sufijo del nombre del derivado junto al original; `null` para el original. */
    public function sufijo(): ?string
    {
        return match ($this) {
            self::Miniatura => 'miniatura',
            self::Media => 'media',
            self::Original => null,
        };
    }

    /** @return list<self> */
    public static function derivadas(): array
    {
        return [self::Miniatura, self::Media];
    }
}
