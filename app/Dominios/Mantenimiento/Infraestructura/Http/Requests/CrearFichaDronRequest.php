<?php

namespace App\Dominios\Mantenimiento\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/fichas-dron` (HU-82, tarea 97). La autorización (permiso
 * `mantenimiento.ficha_dron.crear`) se verifica en el controlador, contra el
 * rol activo — no acá, mismo criterio que el resto del panel.
 *
 * `identificador_dron` valida existencia contra un dron ACTIVO real de
 * `ope_drones` (`Rule::exists()->whereNull('deleted_at')`) — mismo criterio
 * que `equipo_id` en `CrearOrdenMantenimientoRequest`, solo que acá la
 * columna destino es de texto (`identificador`) en vez de `id`. Es la única
 * lectura hacia `ope_drones` de toda esta HU (ADR 0003: sin `belongsTo`
 * cross-módulo).
 *
 * El resto de los campos son datos de activo, todos opcionales salvo el
 * identificador. Los tres accesorios son checkboxes: ausentes cuando no
 * están marcados, por eso `boolean` sin `required` (Laravel no valida un
 * campo ausente que no sea obligatorio).
 */
final class CrearFichaDronRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'identificador_dron' => [
                'required',
                'string',
                'max:40',
                Rule::exists('ope_drones', 'identificador')->whereNull('deleted_at'),
            ],
            'numero_serie' => ['nullable', 'string', 'max:255'],
            'chasis' => ['nullable', 'string', 'max:255'],
            'version_software' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'serie_control' => ['nullable', 'string', 'max:255'],
            'tiene_cargador_control' => ['boolean'],
            'tiene_modem' => ['boolean'],
            'tiene_maletin' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'identificador_dron.required' => __('mantenimiento.validacion.identificador_dron_requerido'),
            'identificador_dron.exists' => __('mantenimiento.validacion.dron_invalido'),
        ];
    }
}
