<?php

namespace App\Dominios\Solicitudes\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Cuerpo de `POST /api/terrenos/{id}/fotos`. */
final class SubirFotoDeTerrenoRequest extends FormRequest
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
            'descripcion' => ['nullable', 'string', 'max:140'],
        ];
    }
}
