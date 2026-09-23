<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Requests;

use App\Dominios\Seguridad\Aplicacion\IniciarVistaComo;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /panel/usuarios/{usuario}/ver-como` (tarea 140). Solo valida forma: el
 * rol bajo el que se quiere ver una cuenta interna es un entero opcional. Que
 * sea de verdad uno de los roles vivos de ESA cuenta lo revalida
 * {@see IniciarVistaComo} contra la base — nunca se confía en que el
 * `<select>` solo ofreciera opciones legítimas.
 *
 * `authorize()` devuelve `true`: la exigencia de sesión la pone `auth:interno`
 * en la ruta y el permiso lo verifican el controlador y el caso de uso contra
 * el ROL ACTIVO (mismo criterio que {@see ActualizarRolActivoRequest}).
 */
class IniciarVistaComoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'rol_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
