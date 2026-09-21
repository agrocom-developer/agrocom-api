<?php

namespace App\Dominios\Mantenimiento\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/ordenes-mantenimiento` (HU-37, tarea 53). La autorización
 * (permiso `mantenimiento.orden.crear`) se verifica en el controlador,
 * contra el rol activo — no acá, mismo criterio que el resto del panel.
 *
 * `equipo_id` valida existencia CONDICIONAL según `equipo_tipo`: apunta a
 * `ope_drones.id` o `man_vehiculos.id`, dos tablas distintas sin una FK real
 * posible (ver docblock de la migración `man_ordenes_mantenimiento`) — mismo
 * criterio de `Rule::exists()` sobre tabla plana que el resto de las FK
 * cross-módulo del panel (`base_id` en `CrearVehiculoRequest`, etc.), solo
 * que acá la tabla destino depende de otro campo del propio formulario.
 */
final class CrearOrdenMantenimientoRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'equipo_tipo' => ['required', Rule::in(['dron', 'vehiculo'])],
            'equipo_id' => [
                'required',
                'integer',
                $this->input('equipo_tipo') === 'dron'
                    ? Rule::exists('ope_drones', 'id')->whereNull('deleted_at')
                    : Rule::exists('man_vehiculos', 'id')->whereNull('deleted_at'),
            ],
            'tipo' => ['required', Rule::in(['preventivo', 'correctivo'])],
            'descripcion' => ['required', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'equipo_tipo.required' => __('mantenimiento.validacion.equipo_tipo_requerido'),
            'equipo_id.required' => __('mantenimiento.validacion.equipo_requerido'),
            'equipo_id.exists' => __('mantenimiento.validacion.equipo_invalido'),
            'tipo.required' => __('mantenimiento.validacion.tipo_orden_requerido'),
            'descripcion.required' => __('mantenimiento.validacion.descripcion_orden_requerida'),
        ];
    }
}
