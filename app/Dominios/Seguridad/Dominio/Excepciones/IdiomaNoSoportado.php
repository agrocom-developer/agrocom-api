<?php

namespace App\Dominios\Seguridad\Dominio\Excepciones;

use RuntimeException;

/**
 * El código de idioma solicitado no está entre los habilitados en esta
 * versión (ADR 0013: español como único idioma disponible en v1). Vive como
 * regla de aplicación — `ActualizarPreferenciaUsuario` — y no como CHECK de
 * base de datos a propósito (ADR 0011, extensión 27/8/2026, punto 8): así se
 * habilita un segundo idioma sin una migración nueva, ampliando la lista de
 * soportados en el caso de uso.
 */
final class IdiomaNoSoportado extends RuntimeException
{
    public static function paraCodigo(string $idioma): self
    {
        return new self("El idioma '{$idioma}' no está habilitado todavía.");
    }
}
