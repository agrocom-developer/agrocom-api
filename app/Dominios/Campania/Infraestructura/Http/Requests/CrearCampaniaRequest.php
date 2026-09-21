<?php

namespace App\Dominios\Campania\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/campanias` (ADR 0015 punto 1, tarea 69). La autorización
 * (permiso `campania.campania.crear`) se verifica en el controlador, contra
 * el rol activo — no acá, mismo criterio que el resto del panel.
 *
 * Sin `cliente_id` (ADR 0015, corregido el 15/9/2026): la campaña es un
 * catálogo compartido — el vínculo con el cliente lo pone el contrato.
 *
 * `codigo` no lleva regla `unique` a propósito: el índice único real es
 * PARCIAL (`cpn_campanias_codigo_unico`, solo entre filas activas), y la
 * regla `unique` de Laravel no lo replica sola sin quedar frágil ante altas
 * y bajas lógicas — la violación se atrapa en `CrearCampania` y se traduce
 * ahí (mismo criterio que `CrearCampoRequest` con el nombre del campo).
 *
 * `estacion` (HU-77, tarea 93) es catálogo cerrado — `Rule::in`, mismo
 * criterio que `estado` en `CambiarEstadoCampaniaRequest` — y `nombre` pasa
 * a `nullable` de verdad: vacío autogenera (ver `CrearCampania`).
 */
final class CrearCampaniaRequest extends FormRequest
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
