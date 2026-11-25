<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Cuerpo de `PUT /api/perfil/foto`. */
final class ActualizarFotoDePerfilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'archivo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:'.(int) config('seguimiento.evidencia_max_kb', 300)],
        ];
    }
}
