<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
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
 *       {
 *         equipo_trabajo_id: 5,
 *         lotes: [{ lote_id: 12, hectareas: '10.00' }, ...],
 *         humedad_min_pct: '40.00', humedad_max_pct: '80.00',
 *         viento_max_kmh: '15.00', temperatura_max_c: '35.00',
 *         velocidad_max_kmh: '20.00', altura_vuelo_m: '3.00',
 *         velocidad_vuelo_kmh: '25.00', ancho_pasada_m: '8.00',
 *       },
 *       ...
 *     ]
 *
 * Con 1 equipo, la vista ya viene con todos los lotes de la orden
 * pre-tildados y su hectáreas por defecto (ver `show.blade.php`); con 2+, el
 * jefe de campo elige a mano — el Request no distingue los dos casos, valida
 * la misma forma siempre.
 *
 * Los 8 campos de límites climáticos y parámetros de vuelo (movidos de
 * `OrdenAplicacion` a `Trabajo`, ver docblock de `CrearOrdenRequest`) se
 * cargan ACÁ, por equipo — hermanos de `equipo_trabajo_id`/`lotes`, no
 * dentro de cada lote: son condiciones del vuelo que hace ESE equipo ese
 * día, no varían lote a lote dentro de la misma salida. Mismos rangos que
 * tenían en `CrearOrdenRequest` cuando vivían en la orden. `nullable`: el
 * jefe de campo puede completar estos datos más tarde, al iniciar la
 * sesión, si no los tiene a mano en este paso.
 *
 * Solo valida FORMA: que cada equipo exista (sin importar el modelo Eloquent
 * de `Personal`, ADR 0003 regla 3 — `exists:` contra la tabla física, mismo
 * criterio que `AsignarIntegranteEquipoRequest`), que no se repita un equipo
 * dentro del mismo submit (`distinct`), que cada lote pertenezca a
 * `ope_orden_lotes` DE ESTA ORDEN, que las hectáreas sean un número
 * positivo, y que los límites climáticos/parámetros de vuelo estén en rango.
 * La vigencia del equipo y el tope de hectáreas POR LOTE NO se validan acá:
 * son las guardas de negocio de `AsignarEquiposOrden`.
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
            'equipos.*.humedad_min_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'equipos.*.humedad_max_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'equipos.*.viento_max_kmh' => ['nullable', 'numeric', 'gt:0'],
            'equipos.*.temperatura_max_c' => ['nullable', 'numeric', 'gt:-10', 'lt:60'],
            'equipos.*.velocidad_max_kmh' => ['nullable', 'numeric', 'gt:0'],
            'equipos.*.altura_vuelo_m' => ['nullable', 'numeric', 'gt:0'],
            'equipos.*.velocidad_vuelo_kmh' => ['nullable', 'numeric', 'gt:0'],
            'equipos.*.ancho_pasada_m' => ['nullable', 'numeric', 'gt:0'],
        ];
    }

    /**
     * Mismo check cruzado que `CrearOrdenRequest::withValidator()` cuando
     * humedad vivía en la orden (`humedad_min_pct <= humedad_max_pct`), acá
     * iterando por cada entrada de `equipos` en vez de por `lotes`.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ((array) $this->input('equipos', []) as $indice => $equipo) {
                $minimo = $equipo['humedad_min_pct'] ?? null;
                $maximo = $equipo['humedad_max_pct'] ?? null;

                if ($minimo !== null && $minimo !== '' && $maximo !== null && $maximo !== '' && (float) $minimo > (float) $maximo) {
                    $validator->errors()->add("equipos.{$indice}.humedad_min_pct", __('operaciones.asignacion_equipos.error_humedad_rango'));
                }
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'equipos.required' => __('operaciones.asignacion_equipos.error_equipos_requerido'),
            'equipos.*.equipo_trabajo_id.required' => __('operaciones.asignacion_equipos.error_equipo_requerido'),
            'equipos.*.lotes.required' => __('operaciones.asignacion_equipos.error_lotes_requerido'),
            'equipos.*.lotes.*.lote_id.required' => __('operaciones.asignacion_equipos.error_lote_requerido'),
            'equipos.*.lotes.*.hectareas.required' => __('operaciones.asignacion_equipos.error_hectareas_requerido'),
        ];
    }
}
