<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/drones` (HU-27, tarea 36). La autorización (permiso
 * `operaciones.dron.crear`) se verifica en el controlador, contra el rol
 * activo — no acá, mismo criterio que el resto del panel.
 *
 * `identificador` no lleva regla `unique` a propósito: el índice único real
 * es PARCIAL (`ope_drones_identificador_unico`, solo entre drones activos), y
 * la regla `unique` de Laravel no lo replica sola sin quedar frágil ante
 * altas y bajas lógicas — la violación se atrapa en `CrearDron` y se traduce
 * ahí (mismo criterio que `CrearClienteRequest` con el NIT).
 *
 * `capacidad_kg` (HU-81, tarea 96), a diferencia de `capacidad_l`, NO lleva
 * `Rule::in()`: no hay un catálogo cerrado de kilos conocido todavía (ver
 * docblock de la migración `add_capacidad_kg_a_ope_drones_table`).
 */
final class CrearDronRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'identificador' => ['required', 'string', 'max:40'],
            'modelo' => ['nullable', 'string', 'max:40'],
            'capacidad_l' => ['nullable', Rule::in([30, 50, 60])],
            'capacidad_kg' => ['nullable', 'numeric', 'min:0.01'],
        ];
    }
}
