<?php

namespace App\Dominios\Finanzas\Aplicacion;

use App\Dominios\Finanzas\Infraestructura\Eloquent\Tarifa;

/**
 * Baja lógica (ADR 0007). Las órdenes de trabajo que la eligieron conservan
 * su copia de montos y la referencia `tarifa_id`; solo deja de ofrecerse.
 */
final class EliminarTarifa
{
    public function ejecutar(Tarifa $tarifa): void
    {
        $tarifa->delete();
    }
}
