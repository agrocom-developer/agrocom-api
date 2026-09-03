<?php

namespace App\Dominios\Mantenimiento\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/ordenes-mantenimiento/{orden}/cerrar` (HU-37, tarea 53). La
 * autorización (permiso `mantenimiento.orden.cerrar`) se verifica en el
 * controlador, contra el rol activo — no acá, mismo criterio que el resto
 * del panel.
 *
 * `repuestos`: al menos una línea — un cierre sin ningún repuesto consumido
 * no genera gasto real, así que se pide explícito en vez de aceptar un
 * cierre "vacío" (decisión de esta tarea, no hay caso de uso en la
 * especificación para un cierre sin consumo).
 *
 * `repuesto_id`/`base_id` validan existencia por tabla plana
 * (`inv_repuestos`/`per_bases`), mismo criterio que `RegistrarMovimientoRequest`
 * de `Inventario` — la guarda real de "alcanza el stock" NO vive acá: la
 * tira `Inventario\Contratos\EscrituraConsumoStock` dentro de la transacción
 * de `MaquinaEstadosOrdenMantenimiento::cerrar()`.
 */
final class CerrarOrdenMantenimientoRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'repuestos' => ['required', 'array', 'min:1'],
            'repuestos.*.repuesto_id' => ['required', 'integer', Rule::exists('inv_repuestos', 'id')->whereNull('deleted_at')],
            'repuestos.*.base_id' => ['required', 'integer', Rule::exists('per_bases', 'id')->whereNull('deleted_at')],
            'repuestos.*.cantidad' => ['required', 'numeric', 'gt:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'repuestos.required' => 'La orden necesita al menos un repuesto consumido para cerrarse.',
            'repuestos.min' => 'La orden necesita al menos un repuesto consumido para cerrarse.',
        ];
    }
}
