<?php

namespace App\Dominios\Finanzas\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * El monto pedido supera el disponible del mes (tope de 3.000 Bs o 70% del
 * devengado, el que sea menor, menos lo ya adelantado — HU-29, tarea 41). El
 * criterio de aceptación pide literal que el rechazo diga cuánto es el
 * máximo disponible, no un "excede el tope" genérico: por eso el mensaje
 * lleva el `$disponible` ya calculado, no solo el monto pedido.
 */
final class AnticipoExcedeTope extends RuntimeException
{
    public static function paraDisponible(string $disponible): self
    {
        return new self(Texto::de('finanzas.errores.anticipo_excede_tope', ['disponible' => $disponible]));
    }
}
