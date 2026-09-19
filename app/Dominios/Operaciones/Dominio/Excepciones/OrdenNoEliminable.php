<?php

namespace App\Dominios\Operaciones\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * Solo una orden `emitida` (todavía sin publicar al catálogo de campo) se da
 * de baja. Cualquier otra ya tiene historia — trabajos, pausas, un cierre o
 * una cancelación con su motivo — y borrarla liberaría su número correlativo
 * (pedido del dueño, 18/9/2026, ADR 0022). Reemplaza a `OrdenVigenteNoEliminable`,
 * que solo frenaba la `vigente`.
 */
final class OrdenNoEliminable extends RuntimeException
{
    public static function porEstado(string $estado): self
    {
        return new self(Texto::de('operaciones.errores.orden_no_eliminable', ['estado' => $estado]));
    }
}
