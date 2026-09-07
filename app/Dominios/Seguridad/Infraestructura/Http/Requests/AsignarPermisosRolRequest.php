<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Requests;

use App\Dominios\Seguridad\Aplicacion\AsignarPermisosRol;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Guardado de la matriz de permisos de un rol
 * (`PUT /panel/roles/{rol}/permisos`).
 *
 * El payload es el set COMPLETO Y DEFINITIVO de permisos deseados, no un
 * delta — es un formulario de checkboxes, y un checkbox desmarcado no se
 * envía: la ausencia ES la revocación. Por eso `permisos` es `nullable` y no
 * `required`: dejar un rol sin ningún permiso es una operación legítima
 * (siempre que las guardas del caso de uso la admitan), y exigir al menos uno
 * la convertiría en un error de validación con un mensaje que no explica
 * nada.
 *
 * `exists` sobre el catálogo VIVO: un id de un permiso dado de baja llegando
 * en el POST no puede insertarse en el pivote — sería un otorgamiento que
 * `CatalogoDePermisos` ni siquiera contaría, invisible en la pantalla que lo
 * creó.
 *
 * Lo que este request NO valida son las cuatro guardas de negocio (escalada,
 * llave propia, permiso huérfano): viven en
 * {@see AsignarPermisosRol} porque
 * dependen de quién es el actor y del estado del resto del catálogo, no de la
 * forma del payload.
 */
final class AsignarPermisosRolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'permisos' => ['nullable', 'array'],
            'permisos.*' => [
                'integer',
                'exists:sec_permission,id',
            ],
        ];
    }
}
