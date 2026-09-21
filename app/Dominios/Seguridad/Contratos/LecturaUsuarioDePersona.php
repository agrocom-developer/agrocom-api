<?php

namespace App\Dominios\Seguridad\Contratos;

/**
 * Frontera de lectura de Seguridad hacia `Personal` (ADR 0003, regla 2): la
 * ficha de una persona muestra, en su resumen relacionado, la cuenta de acceso
 * que tiene vinculada (`sec_user.persona_id`) sin importar `SecUser`.
 */
interface LecturaUsuarioDePersona
{
    /** `$personaId` es el id de `per_personas`. `null` si la persona no tiene una cuenta viva. */
    public function dePersona(int $personaId): ?DatosUsuarioDePersona;
}
