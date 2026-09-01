<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /panel/sesiones/{sesion}/rechazar` (HU-14, tarea 14). La
 * autorización (permiso `operaciones.sesion.validar` + policy
 * validador≠piloto) se verifica en el controlador/caso de uso — no acá,
 * mismo criterio que el resto del panel.
 *
 * El criterio de la HU es literal: "rechazo CON MOTIVO" — sin motivo no hay
 * corrección posible (invariante 2: la fila de `ope_sesion_rechazos` exige
 * `motivo` no vacío también a nivel de esquema).
 */
final class RechazarSesionRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'max:500'],
        ];
    }
}
