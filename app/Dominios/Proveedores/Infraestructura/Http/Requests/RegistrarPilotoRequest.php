<?php

namespace App\Dominios\Proveedores\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Cuerpo de `POST /api/proveedores/pilotos`: el titular de un tercero registra a un piloto. */
final class RegistrarPilotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...ReglasDeCuenta::reglas(),
            'licencia_numero' => ['required', 'string', 'max:40'],
            'licencia_vence' => ['required', 'date', 'after:today'],
            'categoria_habilitacion' => ['required', 'string', 'max:40'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('proveedores.campos');
    }
}
