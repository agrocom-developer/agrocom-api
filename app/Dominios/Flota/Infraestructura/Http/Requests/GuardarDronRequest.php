<?php

namespace App\Dominios\Flota\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Cuerpo de `POST /api/proveedores/drones` y `PUT /api/proveedores/drones/{id}`. */
final class GuardarDronRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return self::reglasDeRegistro();
    }

    /**
     * Las reglas del registro, que el alta manual del panel reutiliza tal cual.
     *
     * @return array<string, mixed>
     */
    public static function reglasDeRegistro(): array
    {
        return [
            'marca' => ['required', 'string', 'max:60'],
            'modelo' => ['required', 'string', 'max:60'],
            'numero_serie' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9._\/-]+$/'],
            // Decimales exactos: viajan como string y se guardan en DECIMAL, nunca float.
            'anio' => ['nullable', 'integer', 'between:1990,2100'],
            'capacidad_tanque_l' => ['required', 'decimal:0,2', 'gt:0', 'max:999.99'],
            'ancho_pasada_m' => ['required', 'decimal:0,2', 'gt:0', 'max:99.99'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('flota.campos');
    }
}
