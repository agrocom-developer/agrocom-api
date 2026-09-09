<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Forma de dato primitiva de una sesión para quien necesita calcular algo
 * sobre ella sin importar el modelo Eloquent `Sesion` (ADR 0003, regla 2):
 * hoy, `Finanzas` (HU-16, tarea 16) para generar el devengo del piloto y su
 * auxiliar. Solo los tres campos que ese cálculo necesita — no es un
 * duplicado de `Sesion`, es el recorte que le corresponde a este contrato.
 */
final readonly class DatosSesionValidada
{
    public function __construct(
        public int $sesionId,
        public int $pilotoId,
        public ?int $auxiliarId,
        public string $hectareasDeclaradas,
    ) {}
}
