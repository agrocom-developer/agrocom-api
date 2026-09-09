<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `PUT /panel/lotes/{lote}` (tarea 77, HU-54, etapa 2). Mismo criterio que
 * `CrearLoteRequest` para `campo_id`/`codigo`/`geometria` (ver su docblock).
 */
final class ActualizarLoteRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'campo_id' => [
                'required',
                'integer',
                Rule::exists('com_campos', 'id')->whereNull('deleted_at'),
            ],
            'codigo' => ['required', 'string', 'max:50'],
            'hectareas' => ['required', 'numeric', 'gt:0'],
            'geometria' => ['nullable', 'string', $this->reglaGeometriaValida()],
            'restricciones' => ['nullable', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'campo_id.required' => 'Seleccioná una propiedad.',
            'campo_id.exists' => 'La propiedad seleccionada no es válida.',
            'hectareas.gt' => 'Las hectáreas tienen que ser mayores a cero.',
        ];
    }

    private function reglaGeometriaValida(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falla): void {
            if (! is_string($valor) || $valor === '') {
                return;
            }

            $decodificado = json_decode($valor, true);

            $esPolygonMinimo = is_array($decodificado)
                && ($decodificado['type'] ?? null) === 'Polygon'
                && is_array($decodificado['coordinates'] ?? null);

            if (! $esPolygonMinimo) {
                $falla('comercial.lotes.error_geometria_invalida')->translate();
            }
        };
    }
}
