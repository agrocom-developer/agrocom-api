<?php

namespace App\Dominios\Personal\Contratos;

/**
 * Lo que `Personal` cuenta de una persona a quien la mira desde otro módulo
 * (ADR 0003, regla 2): quién es, qué puesto tiene y en qué base trabaja.
 * Nunca la tarifa — es dinero, y quien solo necesita ubicar a la persona no
 * tiene por qué verla.
 *
 * `rol` viaja como el valor de `RolOperativoPersona` (`piloto`, …), sin
 * importar el enum: la etiqueta la resuelve quien pinta, con
 * `personal.roles.<valor>`.
 */
final readonly class DatosFichaPersona
{
    public function __construct(
        public int $id,
        public string $nombre,
        public string $rol,
        public ?string $baseNombre,
    ) {}
}
