<?php

namespace App\Dominios\Finanzas\Contratos;

/**
 * Frontera de lectura de Finanzas hacia `Personal` (ADR 0003, regla 2): la
 * ficha de una persona muestra, en su resumen relacionado, cuántos anticipos
 * se le registraron y por cuánto, sin importar el modelo `Anticipo` ni la
 * tabla `fin_anticipos`. Solo anticipos: los devengos de una persona son de
 * esa persona (`finanzas.devengo.ver`), no se consultan desde su ficha.
 */
interface LecturaAnticiposPorPersona
{
    /** `$personaId` es el id de `per_personas`. Sin anticipos, cantidad 0 y monto `0.00`. */
    public function dePersona(int $personaId): DatosAnticiposPersona;
}
