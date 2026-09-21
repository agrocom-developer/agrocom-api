<?php

namespace App\Dominios\Distribucion\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /panel/versiones-apk` (HU-20). La autorización (permiso
 * `distribucion.version.autorizar`) se verifica en el controlador, contra el
 * rol activo — no acá (mismo criterio que el resto del panel).
 */
final class RegistrarVersionApkRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'version' => [
                'required',
                'string',
                'max:20',
                'regex:/^\d+\.\d+\.\d+$/',
                'unique:dis_versiones_apk,version',
            ],
            'version_code' => ['required', 'integer', 'min:1', 'unique:dis_versiones_apk,version_code'],
            'url_apk' => ['required', 'url:https'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'version.required' => __('distribucion.validacion.version_requerida'),
            'version.regex' => __('distribucion.validacion.version_formato'),
            'version_code.required' => __('distribucion.validacion.version_code_requerido'),
            'url_apk.required' => __('distribucion.validacion.url_apk_requerida'),
            'url_apk.url' => __('distribucion.validacion.url_apk_https'),
        ];
    }
}
