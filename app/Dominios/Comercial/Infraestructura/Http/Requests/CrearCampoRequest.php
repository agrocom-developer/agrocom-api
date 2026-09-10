<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/campos` (HU-24, tarea 35; ADR 0018 — el campo ahora cuelga de
 * una propiedad, no directo de un cliente). La autorización (permiso
 * `comercial.campo.crear`) se verifica en el controlador, contra el rol
 * activo — no acá, mismo criterio que el resto del panel.
 *
 * El nombre del campo y el código de cada lote no llevan regla `unique` a
 * propósito: los índices únicos reales son PARCIALES
 * (`com_campos_nombre_unico`, `com_lotes_codigo_unico`, solo entre filas
 * activas), y la regla `unique` de Laravel no los replica sola sin quedar
 * frágil ante altas y bajas lógicas — la violación se atrapa en `CrearCampo`
 * y se traduce ahí (mismo criterio que `CrearClienteRequest` con el NIT).
 *
 * `geometria` (por lote) viaja como STRING (el textarea manda el JSON crudo,
 * sin librería de mapas — prompt de la tarea): se valida como JSON bien
 * formado con la forma mínima de un GeoJSON `Polygon` (`type`/`coordinates`),
 * nunca contra el spec completo de GeoJSON. El perímetro propio del CAMPO
 * (`com_campos.geometria`, ADR 0018) no tiene campo de formulario todavía —
 * el editor de mapa que lo delimita como capa de referencia queda fuera de
 * esta tarea.
 */
final class CrearCampoRequest extends FormRequest
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
            'nombre' => ['required', 'string', 'max:150'],
            'lotes' => ['required', 'array', 'min:1'],
            'lotes.*.codigo' => ['required', 'string', 'max:50'],
            'lotes.*.hectareas' => ['required', 'numeric', 'gt:0'],
            'lotes.*.geometria' => ['nullable', 'string', $this->reglaGeometriaValida()],
            'lotes.*.restricciones' => ['nullable', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'propiedad_id.required' => 'Seleccioná una propiedad.',
            'propiedad_id.exists' => 'La propiedad seleccionada no es válida.',
            'lotes.required' => 'Agregá al menos un lote.',
            'lotes.min' => 'Agregá al menos un lote.',
            'lotes.*.hectareas.gt' => 'Las hectáreas tienen que ser mayores a cero.',
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
                $falla('comercial.campos.error_geometria_invalida')->translate();
            }
        };
    }
}
