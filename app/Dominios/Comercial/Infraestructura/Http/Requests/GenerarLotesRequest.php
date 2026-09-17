<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/propiedades/{propiedad}/lotes/generar` (HU-72 reconstruida,
 * 16/9/2026). La autorización (`comercial.lote.crear`) se verifica en el
 * controlador, contra el rol activo — no acá, mismo criterio que el resto
 * del panel.
 *
 * `terreno.*` son las mismas reglas de terreno que `CrearLoteRequest`
 * (switch `limpio` + grado de obstáculos si no está marcado), pero UN
 * SOLO juego de valores — se aplican a los `cantidad` lotes generados por
 * igual (16/9/2026, pedido directo: no una fila por lote). Sin
 * `codigo`/`hectareas`/`geometria`: el código lo arma `CrearLotesMasivo`
 * con `prefijo` + numeración correlativa, y las hectáreas quedan en un
 * placeholder a corregir después dibujando el polígono.
 *
 * SIN `cultivo_id`/`campania_id` a propósito (corregido tras confundir
 * los dos conceptos): esta pantalla crea ESTRUCTURA, no siembra — el
 * cultivo se asigna después desde la ficha del lote o desde
 * `propiedades/siembra` (`GuardarSiembraCampania`), nunca acá.
 */
final class GenerarLotesRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'prefijo' => ['required', 'string', 'max:30'],
            'cantidad' => ['required', 'integer', 'min:1', 'max:50'],
            'terreno.desnivel' => ['nullable', Rule::in(['ninguno', 'algunos', 'varios', 'empinado'])],
            'terreno.limpio' => ['boolean'],
            'terreno.grado_obstaculos' => [
                Rule::requiredIf(fn () => ! $this->boolean('terreno.limpio')),
                'nullable',
                Rule::in(['pocos_obstaculos', 'algunos_obstaculos', 'muchos_obstaculos']),
            ],
            'terreno.restricciones' => ['nullable', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'prefijo.required' => __('comercial.validacion.lotes_generar_prefijo_requerido'),
            'cantidad.required' => __('comercial.validacion.lotes_generar_cantidad_requerida'),
            'cantidad.min' => __('comercial.validacion.lotes_generar_cantidad_minima'),
            'cantidad.max' => __('comercial.validacion.lotes_generar_cantidad_maxima'),
            'terreno.grado_obstaculos.required' => __('comercial.lotes.error_grado_obstaculos_requerido'),
        ];
    }
}
