<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `PUT /panel/lotes/{lote}` (tarea 77, HU-54, etapa 2). Mismo criterio que
 * `CrearLoteRequest` para `propiedad_id`/`lote.codigo`/`lote.geometria` (ver su
 * docblock).
 */
final class ActualizarLoteRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'propiedad_id' => [
                'required',
                'integer',
                Rule::exists('com_propiedades', 'id')->whereNull('deleted_at'),
            ],
            'lote.codigo' => ['required', 'string', 'max:50'],
            'lote.hectareas' => ['required', 'numeric', 'gt:0'],
            'lote.geometria' => ['nullable', 'string', $this->reglaGeometriaValida()],
            'lote.restricciones' => ['nullable', 'string'],
            'lote.desnivel' => ['nullable', Rule::in(['ninguno', 'algunos', 'varios', 'empinado'])],
            // `limpieza` ya no viaja directo: el formulario manda un switch
            // (`lote.limpio`) + el grado de obstáculos si no está marcado
            // (16/9/2026) — LotesController::normalizarDatos() los combina
            // en el único valor que persiste el modelo.
            'lote.limpio' => ['boolean'],
            'lote.grado_obstaculos' => [
                Rule::requiredIf(fn () => ! $this->boolean('lote.limpio')),
                'nullable',
                Rule::in(['pocos_obstaculos', 'algunos_obstaculos', 'muchos_obstaculos']),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'propiedad_id.required' => __('comercial.validacion.propiedad_requerida'),
            'propiedad_id.exists' => __('comercial.validacion.propiedad_invalida'),
            'lote.codigo.required' => __('comercial.lotes.error_codigo_requerido'),
            'lote.hectareas.required' => __('comercial.lotes.error_hectareas_requeridas'),
            'lote.hectareas.gt' => __('comercial.validacion.hectareas_mayor_a_cero'),
            'lote.grado_obstaculos.required' => __('comercial.lotes.error_grado_obstaculos_requerido'),
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
