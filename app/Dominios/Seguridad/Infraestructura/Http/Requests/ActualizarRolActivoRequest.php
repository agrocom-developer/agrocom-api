<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Requests;

use App\Dominios\Seguridad\Aplicacion\ElegirRolActivo;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Cambio de rol activo sin volver a loguearse (ADR 0004, extensión
 * 27/8/2026, punto 4). Solo valida forma (entero positivo); que `id_role`
 * sea de verdad uno de los roles vivos del usuario lo revalida
 * {@see ElegirRolActivo} contra la base
 * — esta request nunca confía en un `exists:sec_role` a secas porque eso no
 * alcanza a verificar "vivo para ESTE usuario".
 *
 * `authorize()` devuelve `true` y delega la exigencia de sesión autenticada
 * al middleware `auth:interno` de la ruta (mismo criterio que
 * `ListarOrdenesRequest`).
 */
class ActualizarRolActivoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'id_role' => ['required', 'integer', 'min:1'],
            // "Entrar siempre con este rol" (quinta vuelta, maqueta 5c) —
            // opcional: sin el campo, la preferencia no se toca.
            'recordar' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'id_role.required' => __('seguridad.rol.error_id_role_requerido'),
        ];
    }
}
