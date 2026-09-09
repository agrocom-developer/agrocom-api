<?php

namespace App\Dominios\Personal\Dominio\Excepciones;

use App\Dominios\Personal\Dominio\ResultadoSolapamientoVigencias;
use App\Dominios\Personal\Dominio\ValidadorSolapamientoVigencias;
use RuntimeException;

/**
 * Rechazo de {@see ValidadorSolapamientoVigencias}
 * (tarea 72, HU-49, ADR 0015 punto 3): la MISMA persona o el MISMO recurso ya
 * tiene, dentro del MISMO equipo, una vigencia que se pisa con la que se
 * quiere guardar — es la misma fila dos veces, no el préstamo entre
 * cuadrillas (que se avisa, pero se guarda; ver
 * {@see ResultadoSolapamientoVigencias}). El
 * caso de uso que asigna el integrante o el recurso la atrapa y la traduce a
 * un error de validación legible, nunca deja propagarse un 500. Mismo
 * criterio que `VentanasContratoSolapadas` en Comercial.
 */
final class VigenciaEquipoSolapada extends RuntimeException
{
    public static function integrante(string $nombrePersona, string $desde, ?string $hasta): self
    {
        return new self(sprintf(
            '%s ya integra este equipo en una vigencia que se superpone con %s.',
            $nombrePersona,
            self::rango($desde, $hasta),
        ));
    }

    public static function recurso(string $etiquetaRecurso, string $desde, ?string $hasta): self
    {
        return new self(sprintf(
            '%s ya está asignado a este equipo en una vigencia que se superpone con %s.',
            $etiquetaRecurso,
            self::rango($desde, $hasta),
        ));
    }

    private static function rango(string $desde, ?string $hasta): string
    {
        return $desde.'–'.($hasta ?? 'vigente');
    }
}
