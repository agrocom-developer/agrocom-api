<?php

namespace App\Dominios\Finanzas\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * La sesión validada no trae condición de pago (su trabajo no nació de una
 * Orden de Trabajo con tarifa por equipo) y el catálogo no tiene una tarifa
 * predeterminada con la que calcular el devengo. Reemplaza a la vieja
 * `PersonaSinTarifaHa` (ADR 0023): la tarifa ya no es de la persona.
 */
final class TrabajoSinCondicionDePago extends RuntimeException
{
    public static function paraSesion(int $sesionId): self
    {
        return new self(Texto::de('finanzas.errores.trabajo_sin_condicion_de_pago', ['sesion_id' => $sesionId]));
    }
}
