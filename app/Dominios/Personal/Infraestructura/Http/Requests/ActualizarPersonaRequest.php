<?php

namespace App\Dominios\Personal\Infraestructura\Http\Requests;

use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use App\Dominios\Personal\Infraestructura\Http\Requests\Concerns\ValidaDatosDePersona;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `PUT /panel/personas/{persona}` (HU-26, tarea 37). Mismas reglas que
 * `CrearPersonaRequest`; la cédula se compara contra las demás personas,
 * no contra la que se está editando.
 */
final class ActualizarPersonaRequest extends FormRequest
{
    use ValidaDatosDePersona;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $persona = $this->route('persona');

        return $this->reglasDePersona($persona instanceof PerPersona ? $persona->id : null);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return $this->mensajesDePersona();
    }
}
