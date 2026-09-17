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
            'nombre_comun.required' => __('comercial.cultivos.error_nombre_comun_requerido'),
            'tipo_cultivo.required' => __('comercial.validacion.tipo_cultivo_requerido'),
            'tipo_cultivo.enum' => __('comercial.validacion.tipo_cultivo_invalido'),
            'ciclo_vida.required' => __('comercial.validacion.ciclo_vida_requerido'),
            'ciclo_vida.enum' => __('comercial.validacion.ciclo_vida_invalido'),
        ];
    }
}
