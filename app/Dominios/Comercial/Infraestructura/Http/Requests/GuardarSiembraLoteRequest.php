<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Requests;

use App\Dominios\Comercial\Dominio\EtapaCultivo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/lotes/{lote}/siembra` (21/9/2026): la siembra de UN lote en una
 * campaña, desde la ficha del lote. La autorización (permiso
 * `comercial.propiedad.editar`, el mismo de la siembra de la propiedad) se
 * verifica en el controlador, contra el rol activo — no acá.
 *
 * `cultivo_id` en blanco es válido a propósito: es la forma de dejar el lote
 * sin cultivo en esa campaña (`GuardarSiembraCampania` da de baja la siembra
 * que hubiera). Cuando SÍ viene, `hectareas_sembradas` pasa a ser obligatoria:
 * no hay siembra sin superficie. El tope contra las hectáreas del lote lo
 * verifica `Aplicacion/Siembra/GuardarSiembra` con `BigDecimal` (invariante 6).
 */
final class GuardarSiembraLoteRequest extends FormRequest
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
            'cultivo_id' => [
                'nullable',
                'integer',
                Rule::exists('com_cultivos', 'id')->whereNull('deleted_at'),
            ],
            'etapa_cultivo' => ['nullable', Rule::enum(EtapaCultivo::class)],
            'hectareas_sembradas' => ['nullable', 'numeric', 'gt:0', 'required_with:cultivo_id'],
            'fecha_siembra' => ['nullable', 'date'],
            'fecha_cosecha_estimada' => ['nullable', 'date', 'after_or_equal:fecha_siembra'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'campania_id.required' => __('comercial.validacion.siembra_campania_requerida'),
            'campania_id.exists' => __('comercial.contratos.error_campania_invalida'),
            'etapa_cultivo.enum' => __('comercial.validacion.siembra_etapa_invalida'),
            'hectareas_sembradas.gt' => __('comercial.validacion.siembra_hectareas_sembradas_mayor_a_cero'),
            'hectareas_sembradas.required_with' => __('comercial.validacion.siembra_hectareas_sembradas_requeridas'),
            'fecha_cosecha_estimada.after_or_equal' => __('comercial.validacion.siembra_cosecha_estimada_invalida'),
        ];
    }
}
