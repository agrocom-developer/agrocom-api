<?php

namespace App\Dominios\Distribucion\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

/**
 * `POST /panel/versiones-apk` (HU-20). La autorización (permiso
 * `distribucion.version.autorizar`) se verifica en el controlador, contra el
 * rol activo — no acá (mismo criterio que el resto del panel).
 */
final class SubirVersionApkRequest extends FormRequest
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
            'apk' => ['required', File::default()->extensions(['apk'])->max(200 * 1024)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'version.regex' => 'La versión debe seguir el formato SemVer (por ejemplo: 1.4.2).',
        ];
    }
}
