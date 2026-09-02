<?php

namespace App\Dominios\Comercial\Dominio\Excepciones;

use App\Dominios\Comercial\Dominio\ValidadorSolapamientoVentanas;
use RuntimeException;

/**
 * Dos ventanas horarias del mismo contrato se solapan (HU-23, tarea 34) —
 * ver {@see ValidadorSolapamientoVentanas}.
 * El caso de uso que persiste el contrato la atrapa y la traduce a un error
 * de validación legible, nunca deja que el usuario vea un 500.
 */
final class VentanasContratoSolapadas extends RuntimeException
{
    public static function entre(string $horaInicioA, string $horaFinA, string $horaInicioB, string $horaFinB): self
    {
        return new self(
            "La ventana {$horaInicioA}–{$horaFinA} se solapa con la ventana {$horaInicioB}–{$horaFinB}.",
        );
    }
}
