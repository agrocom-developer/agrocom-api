<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `PUT /panel/propiedades/{propiedad}` (ADR 0018). Mismo criterio que
 * `CrearPropiedadRequest` para el nombre (sin regla `unique`, ver su
 * docblock).
 */
final class ActualizarPropiedadRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'cliente_id' => [
                'required',
                'integer',
                Rule::exists('com_clientes', 'id')->whereNull('deleted_at'),
            ],
            'nombre' => ['required', 'string', 'max:150'],
            'ubicacion' => ['nullable', 'string', 'max:255'],
            'departamento' => ['nullable', 'string', 'max:100'],
            'municipio' => ['nullable', 'string', 'max:100'],
            'localidad' => ['nullable', 'string', 'max:150'],
            'latitud' => ['nullable', 'required_with:longitud', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'required_with:latitud', 'numeric', 'between:-180,180'],
            'geometria' => ['nullable', 'string', $this->reglaGeometriaValida()],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'cliente_id.required' => 'Seleccioná un cliente.',
            'cliente_id.exists' => 'El cliente seleccionado no es válido.',
            'latitud.required_with' => __('comercial.propiedades.error_coordenada_incompleta'),
            'longitud.required_with' => __('comercial.propiedades.error_coordenada_incompleta'),
        ];
    }

    private function reglaGeometriaValida(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falla): void {
            if (! is_string($valor) || $valor === '') {
                return;
            }

            $decodificado = json_decode($valor, true);

            $esMultiPolygonMinimo = is_array($decodificado)
                && ($decodificado['type'] ?? null) === 'MultiPolygon'
                && is_array($decodificado['coordinates'] ?? null);

            if (! $esMultiPolygonMinimo) {
                $falla('comercial.propiedades.error_geometria_invalida')->translate();
            }
        };
    }
}
