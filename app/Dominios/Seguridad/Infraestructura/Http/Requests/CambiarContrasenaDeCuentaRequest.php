<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Requests;

use App\Dominios\Seguridad\Contratos\ReglasDeContacto;
use Illuminate\Foundation\Http\FormRequest;

/** `PUT /api/cuenta/contrasena`: el código recibido y la contraseña nueva. */
final class CambiarContrasenaDeCuentaRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:10'],
            'password_nueva' => ReglasDeContacto::reglasDeContrasena(),
        ];
    }
}
