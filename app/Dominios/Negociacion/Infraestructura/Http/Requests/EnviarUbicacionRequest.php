<?php

namespace App\Dominios\Negociacion\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Cuerpo de `POST /api/ubicacion` del piloto. */
final class EnviarUbicacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'solicitud_id' => ['required', 'integer'], 'latitud' => ['required', 'numeric', 'between:-90,90'], 'longitud' => ['required', 'numeric', 'between:-180,180'],
            'precision_m' => ['nullable', 'numeric', 'min:0'], 'capturado_en' => ['nullable', 'date'],
        ];
    }
}
