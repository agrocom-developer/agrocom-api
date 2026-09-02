<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /api/trabajos/{trabajo:uuid_cliente}/acta` (HU-17, tarea 24). El
 * trabajo se identifica por la URL (route-model-binding sobre
 * `uuid_cliente`, no `id`: el dispositivo nunca conoce el autoincremental
 * del servidor para una entidad que él mismo creó — ver runs/24.md); el
 * cuerpo solo lleva el `uuid_cliente` que el dispositivo genera para el
 * ACTA que está pidiendo (invariante 1 de CLAUDE.md).
 */
final class GenerarActaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La autorización real es el permiso `operaciones.acta.generar`,
        // verificado en el controlador contra el token del dispositivo.
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'uuid_cliente' => ['required', 'string', 'max:36'],
        ];
    }
}
