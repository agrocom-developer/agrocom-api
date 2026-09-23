<?php

namespace App\Dominios\Operaciones\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use App\Dominios\Operaciones\Dominio\PoliticaEdicionOrdenTrabajo;
use RuntimeException;

/**
 * Se intentó editar una Orden de Trabajo con TODOS sus trabajos ya
 * `validado` (tarea 127, {@see PoliticaEdicionOrdenTrabajo}): la tanda
 * entera es historia, así que queda cerrada a edición.
 */
final class OrdenTrabajoNoEditable extends RuntimeException
{
    public static function porId(int $ordenTrabajoId): self
    {
        return new self(Texto::de('operaciones.errores.orden_trabajo_no_editable', ['id' => $ordenTrabajoId]));
    }
}
