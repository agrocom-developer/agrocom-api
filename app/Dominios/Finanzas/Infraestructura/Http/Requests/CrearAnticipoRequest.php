<?php

namespace App\Dominios\Finanzas\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/anticipos` (HU-29, tarea 41). La autorización (permiso
 * `finanzas.anticipo.crear`) se verifica en el controlador, contra el rol
 * activo — no acá, mismo criterio que `CrearBaseRequest`.
 *
 * `monto > 0` replica el `CHECK` de
 * `database/migrations/2026_09_02_200001_create_fin_anticipos_table.php` —
 * así el usuario ve un error de validación de Laravel, nunca el
 * `QueryException` crudo de Postgres. El tope (3.000 Bs/70% del devengado)
 * NO se valida acá: es una regla de negocio que depende de sumar otras
 * filas, la ejercita `Aplicacion/RegistrarAnticipo` vía
 * `CalcularDisponibleAnticipo`.
 *
 * `persona_id` se valida por `exists:` contra la tabla física, sin importar
 * el modelo Eloquent de `Personal` (ADR 0003, regla 3, mismo criterio que
 * `CrearOrdenRequest`).
 */
final class CrearAnticipoRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'persona_id' => ['required', 'integer', Rule::exists('per_personas', 'id')->whereNull('deleted_at')],
            'monto' => ['required', 'numeric', 'gt:0'],
            'fecha' => ['required', 'date'],
            'motivo' => ['nullable', 'string', 'max:200'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'persona_id.required' => __('finanzas.anticipos.error_persona_requerida'),
            'persona_id.exists' => __('finanzas.anticipos.error_persona_invalida'),
            'monto.required' => __('finanzas.anticipos.error_monto_requerido'),
            'fecha.required' => __('finanzas.anticipos.error_fecha_requerida'),
        ];
    }
}
