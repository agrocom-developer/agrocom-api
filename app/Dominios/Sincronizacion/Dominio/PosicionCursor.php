<?php

namespace App\Dominios\Sincronizacion\Dominio;

/**
 * Posición del cursor dentro de una sección del catálogo: el par
 * (`updated_at`, `id`) de la última fila entregada. El desempate por `id` es
 * lo que evita repetir o saltear filas que comparten el mismo `updated_at`
 * (ver {@see CursorCatalogo}).
 */
final readonly class PosicionCursor
{
    public function __construct(
        public string $actualizadoEn,
        public int $id,
    ) {}
}
