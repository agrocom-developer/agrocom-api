<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Requests;

use App\Dominios\Operaciones\Dominio\TipoAlojamiento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * `POST /panel/estadias` (reforma 19/9/2026: la oficina registra estadías
 * desde el panel). La autorización (permiso `operaciones.estadia.crear`) se
 * verifica en el controlador, contra el rol activo — no acá, mismo criterio
 * que el resto del panel.
 *
 * `salida` es OPCIONAL: el formulario admite cargar una estadía ya
 * terminada, no solo abrir una en curso (ver docblock de
 * `Aplicacion/RegistrarEstadiaHacienda`). `salida > entrada` NO se valida
 * acá con `after:entrada`, mismo criterio que `RegistrarPausaRequest` con
 * `inicio`/`fin`: la comparación real la hace el caso de uso sobre los
 * valores ya normalizados a UTC.
 *
 * `equipo_trabajo_id`/`propiedad_id`/`vehiculo_id` validan existencia contra
 * la tabla física del módulo dueño (mismo criterio que `RegistrarPausaRequest`
 * con `sesion_id`) — la vigencia de la cuadrilla a la fecha de entrada la
 * revalida `Aplicacion/RegistrarEstadiaHacienda` vía `Contratos/`, no esta regla.
 */
final class RegistrarEstadiaRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'equipo_trabajo_id' => ['required', 'integer', Rule::exists('per_equipos_trabajo', 'id')->whereNull('deleted_at')],
            'propiedad_id' => ['required', 'integer', Rule::exists('com_propiedades', 'id')->whereNull('deleted_at')],
            'entrada' => ['required', 'date'],
            'salida' => ['nullable', 'date'],
            'tipo_alojamiento' => ['required', new Enum(TipoAlojamiento::class)],
            'vehiculo_id' => ['nullable', 'integer', Rule::exists('man_vehiculos', 'id')->whereNull('deleted_at')],
            'observacion' => ['nullable', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'equipo_trabajo_id.required' => __('operaciones.estadias.error_equipo_requerido'),
            'equipo_trabajo_id.exists' => __('operaciones.estadias.error_equipo_invalido'),
            'propiedad_id.required' => __('operaciones.estadias.error_propiedad_requerida'),
            'propiedad_id.exists' => __('operaciones.estadias.error_propiedad_invalida'),
            'entrada.required' => __('operaciones.estadias.error_entrada_requerida'),
            'tipo_alojamiento.required' => __('operaciones.estadias.error_alojamiento_requerido'),
            'tipo_alojamiento.enum' => __('operaciones.estadias.error_alojamiento_invalido'),
            'vehiculo_id.exists' => __('operaciones.estadias.error_vehiculo_invalido'),
        ];
    }
}
