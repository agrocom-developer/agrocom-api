<?php

namespace App\Dominios\Operaciones\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * Se intentó eliminar una orden `vigente` (HU-25, tarea 38). Decisión de
 * esta tarea, documentada en `Aplicacion/EliminarOrden`: una orden vigente
 * puede ya estar en el pull de catálogo de la app de campo con el piloto
 * operando sobre ella — se da de baja recién después de pasar por un estado
 * terminal (`consumida`/`vencida`), que esta tarea todavía no dispara (ver
 * `TransicionesOrden`). Una orden `emitida`, que nunca llegó al catálogo, sí
 * se puede dar de baja libremente.
 */
final class OrdenVigenteNoEliminable extends RuntimeException
{
    public static function porId(int $ordenId): self
    {
        return new self(Texto::de('operaciones.errores.orden_vigente_no_eliminable', ['id' => $ordenId]));
    }
}
