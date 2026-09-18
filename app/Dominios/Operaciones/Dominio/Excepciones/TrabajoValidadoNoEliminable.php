<?php

namespace App\Dominios\Operaciones\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * Se intentó eliminar un trabajo cuyo estado de tablero ya es `validado`
 * (HU-93, tarea 108) — mismo criterio y misma fuente que
 * {@see TrabajoValidadoNoEditable}, separada para un mensaje propio (mismo
 * molde que `OrdenNoEditable`/`OrdenVigenteNoEliminable`).
 */
final class TrabajoValidadoNoEliminable extends RuntimeException
{
    public static function porId(int $trabajoId): self
    {
        return new self(Texto::de('operaciones.errores.trabajo_validado_no_eliminable', ['id' => $trabajoId]));
    }
}
