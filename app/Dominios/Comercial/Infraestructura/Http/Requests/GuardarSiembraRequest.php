<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/propiedades/{propiedad}/siembra` (HU-48, tarea 71, etapa 3;
 * ruta y permiso renombrados por ADR 0020 — antes
 * `panel.campos.{campo}.siembra` / `comercial.campo.editar`, `Campo` ya no
 * existe como entidad). La autorización (permiso `comercial.propiedad.editar`)
 * se verifica en el controlador, contra el rol activo — no acá.
 *
 * `lotes.*.cultivo_id` en blanco es válido a propósito: no todos los lotes
 * de un campo se siembran en la misma campaña. Cuando viene en blanco, el
 * resto de la fila también puede venir vacío (`GuardarSiembraCampania` la
 * interpreta como "sin siembra" y da de baja la que hubiera). Cuando SÍ
 * viene, `hectareas_sembradas` pasa a ser obligatoria: no hay siembra sin
 * superficie.
 *
 * El tope contra las hectáreas del propio lote NO se valida acá (depende de
 * a qué lote pertenece cada fila, dato que este Request no resuelve): lo
 * hace `Aplicacion/Siembra/GuardarSiembra` con `Brick\Math\BigDecimal`
 * (invariante 6), y su excepción se traduce en el controlador — mismo
 * criterio que `CrearCampoRequest` deja el código de lote duplicado para el
 * caso de uso.
 */
final class GuardarSiembraRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'campania_id' => [
                'required',
                'integer',
                Rule::exists('cpn_campanias', 'id')->whereNull('deleted_at'),
            ],
            'lotes' => ['required', 'array', 'min:1'],
            'lotes.*.lote_id' => [
                'required',
                'integer',
                Rule::exists('com_lotes', 'id')->whereNull('deleted_at'),
            ],
            'lotes.*.cultivo_id' => [
                'nullable',
                'integer',
                Rule::exists('com_cultivos', 'id')->whereNull('deleted_at'),
            ],
            'lotes.*.hectareas_sembradas' => ['nullable', 'numeric', 'gt:0', 'required_with:lotes.*.cultivo_id'],
            'lotes.*.fecha_siembra' => ['nullable', 'date'],
            'lotes.*.fecha_cosecha_estimada' => ['nullable', 'date', 'after_or_equal:lotes.*.fecha_siembra'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'campania_id.required' => __('comercial.validacion.siembra_campania_requerida'),
            'campania_id.exists' => __('comercial.contratos.error_campania_invalida'),
            'lotes.required' => __('comercial.siembra.error_lotes_requeridos'),
            'lotes.*.lote_id.required' => __('comercial.siembra.error_lote_id_requerido'),
            'lotes.*.hectareas_sembradas.gt' => __('comercial.validacion.siembra_hectareas_sembradas_mayor_a_cero'),
            'lotes.*.hectareas_sembradas.required_with' => __('comercial.validacion.siembra_hectareas_sembradas_requeridas'),
            'lotes.*.fecha_cosecha_estimada.after_or_equal' => __('comercial.validacion.siembra_cosecha_estimada_invalida'),
        ];
    }
}
