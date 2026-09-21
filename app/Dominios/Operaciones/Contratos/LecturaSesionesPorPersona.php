<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Frontera de lectura de Operaciones hacia `Personal` (ADR 0003, regla 2): la
 * ficha de una persona muestra, en su resumen relacionado, en cuántas sesiones
 * de vuelo participó —como piloto o como auxiliar— sin importar el modelo
 * `Sesion`. Hermano de {@see LecturaDesempenioPersona}, que arma la ficha de
 * desempeño completa; éste solo cuenta.
 */
interface LecturaSesionesPorPersona
{
    /** `$personaId` es el id de `per_personas`. Sin sesiones, todo en cero. */
    public function dePersona(int $personaId): DatosSesionesPersona;
}
