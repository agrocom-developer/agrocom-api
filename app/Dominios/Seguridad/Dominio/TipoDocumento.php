<?php

namespace App\Dominios\Seguridad\Dominio;

/**
 * Documento que identifica a una persona (ADR 0046, punto 7). La persona real es única por
 * `(tipo, número normalizado)`; dos documentos de la misma persona (carnet y pasaporte)
 * crean dos personas, y eso lo detecta la revisión humana, no el índice.
 */
enum TipoDocumento: string
{
    case Ci = 'ci';
    case Pasaporte = 'pasaporte';
    case LicenciaConducir = 'licencia_conducir';

    /** Mayúsculas, sin espacios ni puntos: `7845123 sc` y `7.845.123 SC` son el mismo número. */
    public static function normalizarNumero(string $crudo): string
    {
        return mb_strtoupper((string) preg_replace('/[\s.]+/u', '', trim($crudo)));
    }

    /** Nombre y apellidos para comparar: sin mayúsculas ni espacios de más. */
    public static function normalizarNombre(?string $texto): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $texto)));
    }
}
