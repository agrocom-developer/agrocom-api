<?php

namespace App\Dominios\Personal\Contratos;

/**
 * Frontera de lectura de Personal hacia el perfil de la cuenta (ADR 0003,
 * regla 2): «Mi perfil» muestra los datos de la persona a la que la cuenta
 * está vinculada (`sec_user.persona_id`), en solo lectura.
 *
 * A diferencia de {@see LecturaFichaPersona}, que da un resumen para mostrar a
 * un tercero (un administrador mirando una cuenta), esto incluye documento,
 * celular y dirección: por eso el parámetro es SIEMPRE la persona de la cuenta
 * autenticada, tomada de la sesión — nunca un id de la petición. No hay una
 * lectura «de cualquiera»: por diseño una cuenta no puede pedir los datos de
 * otra persona.
 */
interface LecturaDatosPersonales
{
    /** `null` si la persona no existe o está dada de baja (soft delete). */
    public function dePersona(int $personaId): ?DatosPersonales;
}
