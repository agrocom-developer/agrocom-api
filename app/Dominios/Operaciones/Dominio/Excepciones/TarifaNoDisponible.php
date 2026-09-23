<?php

namespace App\Dominios\Operaciones\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * La tarifa elegida para un equipo de la Orden de Trabajo ya no está en el
 * catálogo de Finanzas (se dio de baja entre que se abrió el formulario y se
 * guardó). ADR 0023.
 */
final class TarifaNoDisponible extends RuntimeException
{
    public static function porId(int $tarifaId): self
    {
        return new self(Texto::de('operaciones.errores.tarifa_no_disponible', ['tarifa_id' => $tarifaId]));
    }
}
