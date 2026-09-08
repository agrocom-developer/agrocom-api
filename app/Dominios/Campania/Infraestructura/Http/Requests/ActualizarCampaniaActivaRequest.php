<?php

namespace App\Dominios\Campania\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Cambio de campaña activa sin volver a loguearse (ADR 0015 punto 1, tarea
 * 69). Solo valida forma (entero positivo); que `id_campania` sea de verdad
 * una campaña viva la revalida {@see
 * \App\Dominios\Campania\Aplicacion\ElegirCampaniaActiva} contra la base —
 * mismo criterio que `ActualizarRolActivoRequest`.
 *
 * `authorize()` devuelve `true` y delega la exigencia de sesión autenticada
 * al middleware `auth:interno` de la ruta.
 */
final class ActualizarCampaniaActivaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'id_campania' => ['required', 'integer', 'min:1'],
        ];
    }
}
