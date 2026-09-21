<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Requests;

use App\Dominios\Operaciones\Dominio\TipoAlojamiento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * `PUT /panel/estadias/{estadia}` (reforma 19/9/2026). La autorización
 * (permiso `operaciones.estadia.editar`) se verifica en el controlador,
 * contra el rol activo — no acá, mismo criterio que el resto del panel.
 *
 * Sin `equipo_trabajo_id`: la cuadrilla no se edita (ver docblock de
 * `Aplicacion/ActualizarEstadiaHacienda`), así que no hay campo que validar
 * acá — cambiarla es, en los hechos, otra estadía.
 */
final class ActualizarEstadiaRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'propiedad_id' => ['required', 'integer', Rule::exists('com_propiedades', 'id')->whereNull('deleted_at')],
            'entrada' => ['required', 'date'],
            'tipo_alojamiento' => ['required', new Enum(TipoAlojamiento::class)],
            'vehiculo_id' => ['nullable', 'integer', Rule::exists('man_vehiculos', 'id')->whereNull('deleted_at')],
            'observacion' => ['nullable', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'propiedad_id.required' => __('operaciones.estadias.error_propiedad_requerida'),
            'propiedad_id.exists' => __('operaciones.estadias.error_propiedad_invalida'),
            'entrada.required' => __('operaciones.estadias.error_entrada_requerida'),
            'tipo_alojamiento.required' => __('operaciones.estadias.error_alojamiento_requerido'),
            'tipo_alojamiento.enum' => __('operaciones.estadias.error_alojamiento_invalido'),
            'vehiculo_id.exists' => __('operaciones.estadias.error_vehiculo_invalido'),
        ];
    }
}
