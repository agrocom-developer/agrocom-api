<?php

namespace App\Dominios\Compartido\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use LogicException;

/**
 * El sistema es un registro de eventos con consecuencias financieras: ningún
 * modelo de dominio admite DELETE físico (ADR 0007, invariante 8 de CLAUDE.md).
 * Quien necesite "borrar" usa el borrado lógico (`delete()` → `deleted_at`);
 * la limpieza física excepcional (p. ej. cumplimiento legal) se resuelve caso
 * por caso fuera de los modelos, con justificación explícita en el PR.
 */
final class BorradoFisicoNoPermitido extends LogicException
{
    public static function paraModelo(string $clase): self
    {
        return new self(Texto::de('ui.errores.borrado_fisico_no_permitido', ['clase' => $clase]));
    }
}
