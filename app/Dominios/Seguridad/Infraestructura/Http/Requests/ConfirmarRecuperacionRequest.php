<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Requests;

use App\Dominios\Seguridad\Contratos\ReglasDeContacto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** `POST /api/auth/recuperar/confirmar`: el código y la contraseña nueva. */
final class ConfirmarRecuperacionRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'identificador' => ['required', 'string', 'max:150'],
            'app' => ['required', 'string', Rule::in(['cliente', 'proveedor', 'piloto'])],
            'codigo' => ['required', 'string', 'max:10'],
            'password_nueva' => ReglasDeContacto::reglasDeContrasena(),
        ];
    }
}
