<?php

namespace App\Dominios\Personal\Contratos;

/**
 * Frontera de lectura de Personal hacia el resumen relacionado de otros
 * módulos (ADR 0003, regla 2): la ficha de una cuenta de `Seguridad` muestra
 * la persona a la que está vinculada (`sec_user.persona_id`) sin importar
 * `PerPersona`. Solo lectura.
 */
interface LecturaFichaPersona
{
    /** `null` si la persona no existe o está dada de baja (soft delete). */
    public function dePersona(int $personaId): ?DatosFichaPersona;
}
