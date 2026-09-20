<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /panel/estadias/{estadia}/finalizar` (reforma 19/9/2026): pasa la
 * estadía de `en_curso` a `finalizada` con su fecha de salida. La
 * autorización (permiso `operaciones.estadia.editar`, cubre finalizar) se
 * verifica en el controlador, contra el rol activo — no acá.
 *
 * `salida > entrada` NO se valida acá con `after:`, mismo criterio que
 * `RegistrarEstadiaRequest`: la comparación real la hace
 * `Aplicacion/FinalizarEstadiaHacienda` sobre los valores ya normalizados a UTC.
 */
final class FinalizarEstadiaRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'salida' => ['required', 'date'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'salida.required' => __('operaciones.estadias.error_salida_requerida'),
        ];
    }
}
