<?php

namespace App\Dominios\Flota\Infraestructura\Http\Requests;

use App\Dominios\Flota\Aplicacion\GestionarFotosDeDron;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Cuerpo de `POST /api/drones/{id}/fotos`. */
final class SubirFotoDeDronRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::in(GestionarFotosDeDron::TIPOS)],
            'archivo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:'.(int) config('seguimiento.evidencia_max_kb', 300)],
        ];
    }
}
