<?php

namespace App\Dominios\Inventario\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /panel/repuestos` (HU-36, tarea 52). La autorización (permiso
 * `inventario.repuesto.crear`) se verifica en el controlador, contra el rol
 * activo — no acá, mismo criterio que el resto del panel.
 *
 * `costo_unitario` es opcional en el alta: un repuesto puede darse de alta
 * sin haber tenido todavía ninguna compra (ver docblock de `inv_repuestos`).
 */
final class CrearRepuestoRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:60'],
            'descripcion' => ['required', 'string', 'max:255'],
            'unidad' => ['required', 'string', 'max:30'],
            'costo_unitario' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
