<?php

namespace App\Dominios\Mantenimiento\Infraestructura\Http\Requests;

use App\Dominios\Mantenimiento\Dominio\EstadoVehiculo;
use App\Dominios\Mantenimiento\Dominio\TipoCombustibleVehiculo;
use App\Dominios\Mantenimiento\Dominio\TipoVehiculo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/vehiculos` (HU-40, tarea 50). La autorización (permiso
 * `mantenimiento.vehiculo.crear`) se verifica en el controlador, contra el
 * rol activo — no acá, mismo criterio que el resto del panel.
 *
 * `base_id` es opcional (FK nullable) — un vehículo sin base asignada es un
 * caso válido. Cuando viene, tiene que apuntar a una base VIVA
 * (`whereNull('deleted_at')`), mismo criterio que `base_id` en
 * `CrearPersonaRequest`.
 *
 * `marca`/`modelo`/`anio`/`combustible`/`es_4x4`/`kilometraje_inicial`/
 * `kilometraje_actual` (HU-84, tarea 99) completan la ficha, todos
 * opcionales. `kilometraje_inicial` es editable ya en el alta y también en
 * `ActualizarVehiculoRequest` — a diferencia de `ciclos_inicial` en
 * baterías (tarea 98), esta HU no lo pide inmutable.
 *
 * `tipo` (HU-90, tarea 105): catálogo cerrado, opcional — mismo criterio de
 * validación que `combustible`.
 */
final class CrearVehiculoRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'identificador' => ['required', 'string', 'max:40'],
            'tipo' => ['nullable', Rule::enum(TipoVehiculo::class)],
            'marca' => ['nullable', 'string', 'max:60'],
            'modelo' => ['nullable', 'string', 'max:60'],
            'anio' => ['nullable', 'integer'],
            'combustible' => ['nullable', Rule::enum(TipoCombustibleVehiculo::class)],
            'es_4x4' => ['boolean'],
            'kilometraje_inicial' => ['nullable', 'numeric', 'min:0'],
            'kilometraje_actual' => ['nullable', 'numeric', 'min:0'],
            'base_id' => [
                'nullable',
                'integer',
                Rule::exists('per_bases', 'id')->whereNull('deleted_at'),
            ],
            'estado' => ['required', Rule::enum(EstadoVehiculo::class)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'identificador.required' => __('mantenimiento.validacion.vehiculo_identificador_requerido'),
            'estado.required' => __('mantenimiento.validacion.vehiculo_estado_requerido'),
            'base_id.exists' => __('mantenimiento.validacion.base_invalida'),
        ];
    }
}
