<?php

namespace App\Dominios\Flota\Contratos;

/**
 * Lectura de drones para otros módulos (ADR 0003, regla 2): `Proveedores`
 * necesita el dueño de un dron para avisarle de un documento por vencer, y la
 * asignación (ADR 0030) pedirá los equipos aptos por este mismo contrato.
 */
interface LecturaDrones
{
    public function dron(int $id): ?DatosDron;
}
