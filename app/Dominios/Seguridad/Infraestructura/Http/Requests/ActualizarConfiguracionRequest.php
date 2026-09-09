<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /panel/configuracion/{grupo}` (tarea 78, HU-55). `valores`/`borrar`
 * llegan como array `clave => ...` (el HTML usa `name="valores[clave]"`,
 * nunca `name="{{ $clave }}"` a secas: un nombre de campo con puntos
 * literales, `mapas.google_maps_api_key`, PHP lo reescribe con guiones bajos
 * antes de que llegue a `$_POST` — el array evita ese problema).
 */
final class ActualizarConfiguracionRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'valores' => ['present', 'array'],
            'valores.*' => ['nullable', 'string', 'max:1000'],
            'borrar' => ['array'],
            'borrar.*' => ['boolean'],
        ];
    }
}
