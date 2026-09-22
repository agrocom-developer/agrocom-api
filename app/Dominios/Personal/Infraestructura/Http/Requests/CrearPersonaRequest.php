<?php

namespace App\Dominios\Personal\Infraestructura\Http\Requests;

use App\Dominios\Personal\Infraestructura\Http\Requests\Concerns\ValidaDatosDePersona;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /panel/personas` (HU-26, tarea 37). La autorización (permiso
 * `personal.persona.crear`) se verifica en el controlador, contra el rol
 * activo — no acá, mismo criterio que el resto del panel. Las reglas viven
 * en `ValidaDatosDePersona`, compartidas con la edición.
 */
final class CrearPersonaRequest extends FormRequest
{
    use ValidaDatosDePersona;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->reglasDePersona();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return $this->mensajesDePersona();
    }
}
