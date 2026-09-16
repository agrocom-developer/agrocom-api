<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Requests;

use App\Dominios\Comercial\Dominio\CicloVidaCultivo;
use App\Dominios\Comercial\Dominio\TipoCultivo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `PUT /panel/cultivos/{cultivo}` (HU-48, tarea 71). Mismas reglas que
 * `CrearCultivoRequest`.
 */
final class ActualizarCultivoRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nombre_comun' => ['required', 'string', 'max:80'],
            'nombre_cientifico' => ['nullable', 'string', 'max:150'],
            'tipo_cultivo' => ['required', Rule::enum(TipoCultivo::class)],
            'ciclo_vida' => ['required', Rule::enum(CicloVidaCultivo::class)],
            'notas_agronomicas' => ['nullable', 'string', 'max:2000'],
            'activo' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'tipo_cultivo.required' => 'Seleccioná el tipo de cultivo.',
            'tipo_cultivo.enum' => 'El tipo de cultivo no es válido.',
            'ciclo_vida.required' => 'Seleccioná el ciclo de vida.',
            'ciclo_vida.enum' => 'El ciclo de vida no es válido.',
        ];
    }
}
