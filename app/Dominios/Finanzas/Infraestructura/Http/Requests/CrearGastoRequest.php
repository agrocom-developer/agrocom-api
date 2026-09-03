<?php

namespace App\Dominios\Finanzas\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/gastos` (HU-33, tarea 47). La autorización (permiso
 * `finanzas.gasto.crear`) se verifica en el controlador, contra el rol
 * activo — no acá, mismo criterio que `CrearAnticipoRequest`.
 *
 * `cantidad`/`precio_unitario` > 0 replican los `CHECK` de
 * `database/migrations/2026_09_03_100003_create_fin_gastos_table` — así el
 * usuario ve un error de validación de Laravel, nunca el `QueryException`
 * crudo de Postgres.
 *
 * `comprobante` es OPCIONAL: la especificación completa sugiere que sea
 * obligatorio según el `medio_pago` del gasto (p. ej. efectivo sin
 * comprobante posible en algunos casos), pero esa columna queda fuera de
 * esta tarea (ver el prompt de la HU-33) — sin ese dato, no hay regla real
 * que sostenga cuándo exigirlo, así que se deja opcional. Tipo (imagen o
 * PDF) y tamaño máximo (10 MB, generoso para una foto de un comprobante de
 * papel) sí se validan siempre que el archivo venga: mismo criterio de
 * "sobre" que `SubirEvidenciaRequest`, pero acá SÍ es un 422 estándar de
 * Laravel — no hay vocabulario `rechazado` de sync que preservar, esta
 * subida no participa del motor de sync.
 *
 * `subrubro_id` valida solo que exista (no que pertenezca al `rubro_id`
 * elegido): el CA esencial es "categorías de catálogo", no una guarda de
 * consistencia rubro↔subrubro — el `<select>` de la vista ya filtra por
 * rubro en JS, así que un descalce solo puede venir de un POST manual.
 */
final class CrearGastoRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date'],
            'rubro_id' => ['required', 'integer', Rule::exists('fin_rubros', 'id')->whereNull('deleted_at')],
            'subrubro_id' => ['nullable', 'integer', Rule::exists('fin_subrubros', 'id')->whereNull('deleted_at')],
            'cantidad' => ['required', 'numeric', 'gt:0'],
            'precio_unitario' => ['required', 'numeric', 'gt:0'],
            'base_id' => ['nullable', 'integer', Rule::exists('per_bases', 'id')->whereNull('deleted_at')],
            'trabajo_id' => ['nullable', 'integer', Rule::exists('ope_trabajos', 'id')->whereNull('deleted_at')],
            'comprobante' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'rubro_id.required' => __('finanzas.gastos.error_rubro_requerido'),
            'rubro_id.exists' => __('finanzas.gastos.error_rubro_invalido'),
            'comprobante.mimes' => __('finanzas.gastos.error_comprobante_tipo'),
            'comprobante.max' => __('finanzas.gastos.error_comprobante_tamano'),
        ];
    }
}
