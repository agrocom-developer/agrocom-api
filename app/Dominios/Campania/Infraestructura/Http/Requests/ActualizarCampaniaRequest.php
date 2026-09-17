<?php

namespace App\Dominios\Campania\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `PUT /panel/campanias/{campania}` (ADR 0015 punto 1, tarea 69). Mismas
 * reglas que `CrearCampaniaRequest` — ver ese docblock para el porqué de que
 * `codigo` no lleve `unique` y de que no haya `cliente_id`.
 *
 * `nombre` en blanco NO autogenera ni vacía acá (HU-77, tarea 93): a
 * diferencia del alta, `ActualizarCampania` preserva el nombre existente
 * cuando llega `null` — ver su docblock.
 */
final class ActualizarCampaniaRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:20'],
            'nombre' => ['nullable', 'string', 'max:150'],
            'estacion' => ['required', Rule::in(['invierno', 'verano'])],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
        ];
    }

    /**
     * Solo lo que merece decir algo MÁS específico que el genérico de
     * `lang/es/validation.php` ("Este campo es obligatorio."). El resto de las
     * reglas (`max`, `date`, `in`) cae a ese catálogo. `required` ya cubre
     * nulo, vacío y solo espacios: los middlewares globales `TrimStrings` y
     * `ConvertEmptyStringsToNull` normalizan el dato antes de validar.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'codigo.required' => __('campania.campanias.error_codigo_requerido'),
            'estacion.required' => __('campania.campanias.error_estacion_requerida'),
            'fecha_inicio.required' => __('campania.campanias.error_fecha_inicio_requerida'),
            'fecha_fin.required' => __('campania.campanias.error_fecha_fin_requerida'),
            'fecha_fin.after_or_equal' => __('campania.campanias.error_fechas_rango'),
        ];
    }
}
