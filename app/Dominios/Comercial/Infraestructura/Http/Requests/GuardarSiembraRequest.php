<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/campos/{campo}/siembra` (HU-48, tarea 71, etapa 3). La
 * autorización (permiso `comercial.campo.editar`) se verifica en el
 * controlador, contra el rol activo — no acá.
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
            'campania_id.required' => 'Seleccioná la campaña.',
            'campania_id.exists' => 'La campaña seleccionada no es válida.',
            'lotes.*.hectareas_sembradas.gt' => 'Las hectáreas sembradas tienen que ser mayores a cero.',
            'lotes.*.hectareas_sembradas.required_with' => 'Indicá las hectáreas sembradas de ese lote.',
            'lotes.*.fecha_cosecha_estimada.after_or_equal' => 'La cosecha estimada no puede ser anterior a la siembra.',
        ];
    }
}
