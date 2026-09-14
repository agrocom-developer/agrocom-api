<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/asignacion-equipos/{orden}` (HU-70, tarea 85). La autorización
 * (permiso `operaciones.orden.asignar_equipos`) se verifica en el controlador,
 * contra el rol activo — no acá, mismo criterio que el resto del panel.
 *
 * Solo valida FORMA: que el equipo exista (sin importar el modelo Eloquent de
 * `Personal`, ADR 0003 regla 3 — `exists:` contra la tabla física, mismo
 * criterio que `AsignarIntegranteEquipoRequest`) y que las hectáreas sean un
 * número positivo. La vigencia del equipo y el tope de hectáreas del lote NO
 * se validan acá: son las guardas de negocio de `AsignarEquiposOrden`.
 */
final class AsignarEquipoOrdenRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'equipo_trabajo_id' => [
                'required',
                'integer',
                Rule::exists('per_equipos_trabajo', 'id')->whereNull('deleted_at'),
            ],
            'hectareas' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
