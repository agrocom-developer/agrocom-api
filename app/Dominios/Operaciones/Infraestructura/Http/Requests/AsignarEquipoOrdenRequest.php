<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/asignacion-equipos/{orden}` (HU-70, tarea 85; rediseñado por
 * HU-92, tarea 107: confirmación en bloque de N equipos, cada uno con sus
 * lotes y hectáreas). La autorización (permiso
 * `operaciones.orden.asignar_equipos`) se verifica en el controlador, contra
 * el rol activo — no acá, mismo criterio que el resto del panel.
 *
 * Forma del payload — un solo submit para toda la confirmación:
 *
 *     equipos: [
 *       { equipo_trabajo_id: 5, lotes: [{ lote_id: 12, hectareas: '10.00' }, ...] },
 *       ...
 *     ]
 *
 * Con 1 equipo, la vista ya viene con todos los lotes de la orden
 * pre-tildados y su hectáreas por defecto (ver `show.blade.php`); con 2+, el
 * jefe de campo elige a mano — el Request no distingue los dos casos, valida
 * la misma forma siempre.
 *
 * Solo valida FORMA: que cada equipo exista (sin importar el modelo Eloquent
 * de `Personal`, ADR 0003 regla 3 — `exists:` contra la tabla física, mismo
 * criterio que `AsignarIntegranteEquipoRequest`), que no se repita un equipo
 * dentro del mismo submit (`distinct`), que cada lote pertenezca a
 * `ope_orden_lotes` DE ESTA ORDEN, y que las hectáreas sean un número
 * positivo. La vigencia del equipo y el tope de hectáreas POR LOTE NO se
 * validan acá: son las guardas de negocio de `AsignarEquiposOrden`.
 */
final class AsignarEquipoOrdenRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $ordenId = $this->route('orden')?->id;

        return [
            'equipos' => ['required', 'array', 'min:1'],
            'equipos.*.equipo_trabajo_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('per_equipos_trabajo', 'id')->whereNull('deleted_at'),
            ],
            'equipos.*.lotes' => ['required', 'array', 'min:1'],
            'equipos.*.lotes.*.lote_id' => [
                'required',
                'integer',
                Rule::exists('ope_orden_lotes', 'lote_id')->where('orden_id', $ordenId)->whereNull('deleted_at'),
            ],
            'equipos.*.lotes.*.hectareas' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
