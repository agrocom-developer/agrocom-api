<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Requests;

use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `PUT /panel/campos/{campo}` (HU-24, tarea 35). Mismo criterio que
 * `CrearCampoRequest` para nombre/código (sin regla `unique`, ver su
 * docblock) y para `geometria` (JSON, forma mínima de GeoJSON `Polygon`).
 *
 * `lotes.*.id`, cuando viene, tiene que pertenecer AL PROPIO campo que se
 * está editando — nunca a otro (mismo espíritu que la regla del portal del
 * cliente, invariante 5 de CLAUDE.md, aplicada acá al panel interno; mismo
 * criterio que `ActualizarClienteRequest` con `contactos.*.id`).
 */
final class ActualizarCampoRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Campo|null $campo */
        $campo = $this->route('campo');
        $campoId = $campo?->id;

        return [
            'propiedad_id' => [
                'required',
                'integer',
                Rule::exists('com_propiedades', 'id')->whereNull('deleted_at'),
            ],
            'nombre' => ['required', 'string', 'max:150'],
            'lotes' => ['required', 'array', 'min:1'],
            'lotes.*.id' => [
                'nullable',
                'integer',
                Rule::exists('com_lotes', 'id')
                    ->where('campo_id', $campoId)
                    ->whereNull('deleted_at'),
            ],
            'lotes.*.codigo' => ['required', 'string', 'max:50'],
            'lotes.*.hectareas' => ['required', 'numeric', 'gt:0'],
            'lotes.*.geometria' => ['nullable', 'string', $this->reglaGeometriaValida()],
            'lotes.*.restricciones' => ['nullable', 'string'],
            'lotes.*.desnivel' => ['nullable', Rule::in(['ninguno', 'algunos', 'varios', 'empinado'])],
            'lotes.*.limpieza' => ['nullable', Rule::in(['limpio', 'algunos_obstaculos', 'muchos_obstaculos'])],
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
            'lotes.*.id.exists' => 'Uno de los lotes enviados no pertenece a este campo.',
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
