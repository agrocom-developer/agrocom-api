<?php

namespace App\Dominios\Operaciones\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * Se intentó editar un trabajo cuyo estado de tablero ya es `validado`
 * (HU-93, tarea 108): criterio por defecto escrito en
 * `docs/negocio/observaciones_operaciones_comercial_2026-09-13.md` §4.4 —
 * "eliminar/editar libre solo antes de validar". Invariante 2 de CLAUDE.md:
 * un registro validado nunca se sobrescribe; la vía correcta para un trabajo
 * ya validado es una corrección con `anula_a_id`, todavía sin exponer como
 * botón (fuera de alcance de esta tarea).
 */
final class TrabajoValidadoNoEditable extends RuntimeException
{
    public static function porId(int $trabajoId): self
    {
        return new self(Texto::de('operaciones.errores.trabajo_validado_no_editable', ['id' => $trabajoId]));
    }
}
