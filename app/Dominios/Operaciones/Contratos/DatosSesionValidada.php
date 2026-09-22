<?php

namespace App\Dominios\Operaciones\Contratos;

use App\Dominios\Finanzas\Contratos\CondicionPago;

/**
 * Lo que Finanzas necesita de una sesión validada para devengar (ADR 0023):
 * quiénes trabajaron, cuántas hectáreas, qué día, y con qué condición de
 * pago —la del equipo en la Orden de Trabajo del trabajo de la sesión, o
 * `null` si ese trabajo no la tiene (Finanzas cae a la tarifa predeterminada).
 *
 * `fecha` es el día de la sesión (`inicio`), en `Y-m-d`: es la clave del
 * jornal, no el día en que alguien la validó.
 */
final readonly class DatosSesionValidada
{
    public function __construct(
        public int $sesionId,
        public int $trabajoId,
        public int $pilotoId,
        public ?int $auxiliarId,
        public string $hectareasDeclaradas,
        public string $fecha,
        public ?CondicionPago $condicionPago,
    ) {}
}
