<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/facturas` (HU-31, tarea 45). La autorización (permiso
 * `comercial.factura.crear`) se verifica en el controlador, contra el rol
 * activo — no acá, mismo criterio que `CrearAnticipoRequest`.
 *
 * `acta_id` se valida por `exists:` contra la tabla física `ope_actas`, sin
 * importar el modelo Eloquent de `Operaciones` (ADR 0003, regla 3, mismo
 * criterio que `persona_id` en `CrearAnticipoRequest`) — es solo un chequeo
 * de existencia por ID, no lógica cruzada. Que esté `firmada` y sin factura
 * previa son reglas de negocio reales, las valida `Aplicacion/EmitirFactura`
 * vía `ActaNoFacturable`.
 */
final class EmitirFacturaRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'acta_id' => ['required', 'integer', Rule::exists('ope_actas', 'id')->whereNull('deleted_at')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'acta_id.required' => __('comercial.facturas.error_acta_requerida'),
            'acta_id.exists' => __('comercial.facturas.error_acta_invalida'),
        ];
    }
}
