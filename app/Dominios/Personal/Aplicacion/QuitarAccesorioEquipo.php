<?php

namespace App\Dominios\Personal\Aplicacion;

use App\Dominios\Personal\Infraestructura\Eloquent\EquipoAccesorio;

/**
 * Quita un accesorio de una cuadrilla (tarea "cuadrillas-estadias", 19/9/2026):
 * soft delete, nunca un `DELETE` físico (ADR 0007) — a diferencia de
 * integrantes y recursos, un accesorio no tiene vigencia que finalizar, así
 * que quitarlo es directamente darlo de baja.
 */
final class QuitarAccesorioEquipo
{
    public function ejecutar(EquipoAccesorio $equipoAccesorio): void
    {
        $equipoAccesorio->delete();
    }
}
