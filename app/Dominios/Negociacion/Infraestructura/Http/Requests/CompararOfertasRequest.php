<?php

namespace App\Dominios\Negociacion\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query de `GET /api/solicitudes/{id}/ofertas` (el comparador del cliente, ADR 0049):
 * orden y filtros opcionales. Solo reducen u ordenan lo que el cliente ya ve.
 */
final class CompararOfertasRequest extends FormRequest
{
    public const ORDENES = ['recomendado', 'precio', 'calificacion', 'reciente'];

    public const TIPOS_DE_PROVEEDOR = ['propio', 'independiente', 'tercero'];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return self::reglas();
    }

    /** @return array<string, mixed> */
    public static function reglas(): array
    {
        return [
            'orden' => ['nullable', Rule::in(self::ORDENES)],
            'precio_ha_min' => ['nullable', 'numeric', 'gte:0', 'max:99999999', 'decimal:0,4'],
            'precio_ha_max' => ['nullable', 'numeric', 'gte:0', 'max:99999999', 'decimal:0,4'],
            'monto_max' => ['nullable', 'numeric', 'gte:0', 'max:9999999999999', 'decimal:0,2'],
            'calificacion_min' => ['nullable', 'numeric', 'between:0,5', 'decimal:0,2'],
            'tipo_proveedor' => ['nullable', Rule::in(self::TIPOS_DE_PROVEEDOR)],
            'capacidad_tanque_min' => ['nullable', 'numeric', 'gte:0', 'max:99999', 'decimal:0,2'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('negociacion.campos');
    }
}
