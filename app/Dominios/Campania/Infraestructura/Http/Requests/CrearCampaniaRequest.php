<?php

namespace App\Dominios\Campania\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /panel/campanias` (ADR 0015 punto 1, tarea 69). La autorización
 * (permiso `campania.campania.crear`) se verifica en el controlador, contra
 * el rol activo — no acá, mismo criterio que el resto del panel.
 *
 * `codigo` no lleva regla `unique` a propósito: el índice único real es
 * PARCIAL (`cpn_campanias_codigo_unico`, solo entre filas activas), y la
 * regla `unique` de Laravel no lo replica sola sin quedar frágil ante altas y
 * bajas lógicas — la violación se atrapa en `CrearCampania` y se traduce ahí
 * (mismo criterio que `CrearCampoRequest` con el nombre del campo).
 */
final class CrearCampaniaRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:20'],
            'nombre' => ['nullable', 'string', 'max:150'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
        ];
    }
}
