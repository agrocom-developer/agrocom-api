<?php

namespace App\Dominios\Personal\Infraestructura\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/cuadrillas/{equipoTrabajo}/accesorios` (tarea
 * "cuadrillas-estadias", 19/9/2026): agrega un accesorio del catálogo
 * (`accesorio_id`) o uno nuevo por nombre (`nombre_nuevo`) — exactamente uno
 * de los dos, nunca los dos ni ninguno; `AgregarAccesorioEquipo` decide si
 * crea el accesorio o actualiza la cantidad de uno ya asignado.
 */
final class AgregarAccesorioEquipoRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'accesorio_id' => [
                'required_without:nombre_nuevo',
                'nullable',
                'integer',
                Rule::exists('per_accesorios', 'id')->whereNull('deleted_at'),
            ],
            'nombre_nuevo' => ['required_without:accesorio_id', 'nullable', 'string', 'max:80'],
            'cantidad' => ['required', 'integer', 'min:1'],
            'observacion' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('accesorio_id') && $this->filled('nombre_nuevo')) {
                $validator->errors()->add('nombre_nuevo', __('personal.equipos_trabajo.error_accesorio_ambos'));
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'accesorio_id.required_without' => __('personal.equipos_trabajo.error_accesorio_requerido'),
            'accesorio_id.exists' => __('personal.validacion.accesorio_invalido'),
            'nombre_nuevo.required_without' => __('personal.equipos_trabajo.error_accesorio_requerido'),
            'cantidad.required' => __('personal.equipos_trabajo.error_accesorio_cantidad_requerida'),
            'cantidad.min' => __('personal.equipos_trabajo.error_accesorio_cantidad_requerida'),
        ];
    }
}
