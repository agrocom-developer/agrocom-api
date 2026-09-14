<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/lotes` (tarea 77, HU-54, etapa 2). La autorización (permiso
 * `comercial.lote.crear`) se verifica en el controlador, contra el rol
 * activo — no acá, mismo criterio que el resto del panel.
 *
 * `campo_id` es la propiedad dueña del lote: el select de cliente de la
 * ficha es solo para filtrar el select de propiedad en el cliente (JS,
 * `resources/js/pages/lotes-form.js`), no viaja como columna propia — un
 * lote no tiene `cliente_id`, lo hereda de su campo.
 *
 * `codigo`/`hectareas`/`geometria`/`restricciones` viajan anidados bajo
 * `lote[...]`: el formulario reusa `campos/_lote-fila.blade.php` con
 * `prefijo: 'lote'` (ver su docblock) en vez de envolver un único lote en
 * un array de uno, así que las reglas tienen que validar `lote.codigo`,
 * no `codigo` suelto — de lo contrario el `required` nunca encuentra el
 * dato y el guardado falla en silencio.
 *
 * El código no lleva regla `unique` a propósito: el índice único real es
 * PARCIAL (`com_lotes_codigo_unico`, solo entre lotes activos del mismo
 * campo) — la violación se atrapa en `CrearLote` (vía `GuardadoLote`) y se
 * traduce ahí, mismo criterio que `CrearCampoRequest`.
 *
 * `geometria` y su regla de forma mínima de GeoJSON `Polygon`: mismo criterio
 * que `CrearCampoRequest`.
 */
final class CrearLoteRequest extends FormRequest
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
            'lote.codigo' => ['required', 'string', 'max:50'],
            'lote.hectareas' => ['required', 'numeric', 'gt:0'],
            'lote.geometria' => ['nullable', 'string', $this->reglaGeometriaValida()],
            'lote.restricciones' => ['nullable', 'string'],
            'lote.desnivel' => ['nullable', Rule::in(['ninguno', 'algunos', 'varios', 'empinado'])],
            'lote.limpieza' => ['nullable', Rule::in(['limpio', 'algunos_obstaculos', 'muchos_obstaculos'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'campo_id.required' => 'Seleccioná una propiedad.',
            'campo_id.exists' => 'La propiedad seleccionada no es válida.',
            'lote.hectareas.gt' => 'Las hectáreas tienen que ser mayores a cero.',
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
