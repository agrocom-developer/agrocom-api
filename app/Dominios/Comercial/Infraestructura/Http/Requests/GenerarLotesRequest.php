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
 * `campania_id` sin guarda de cliente (ADR 0015, corregido el 15/9/2026):
 * la campaña es un catálogo compartido, cualquier lote puede sembrarse en
 * cualquier campaña.
 */
final class GenerarLotesRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'cantidad' => ['required', 'integer', 'min:1', 'max:50'],
            'cultivo_id' => ['nullable', 'integer', Rule::exists('com_cultivos', 'id')],
            'campania_id' => ['nullable', 'integer', Rule::exists('cpn_campanias', 'id')->whereNull('deleted_at')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'cantidad.required' => 'Indicá cuántos lotes generar.',
            'cantidad.min' => 'Generá al menos un lote.',
            'cantidad.max' => 'No se pueden generar más de 50 lotes a la vez.',
        ];
    }
}
