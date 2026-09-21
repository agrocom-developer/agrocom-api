<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Requests\Concerns;

use App\Dominios\Operaciones\Dominio\ProductoCalda;
use App\Dominios\Operaciones\Dominio\TipoInsumo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * Reglas compartidas por `AsignarEquipoOrdenRequest` (`/panel/reparto-cuadrillas/{orden}`,
 * pantalla vieja — HU-70/92) y `CrearOrdenTrabajoRequest` (`/panel/trabajos`,
 * pantalla nueva de la reforma 18/9/2026): ambas terminan llamando a
 * `Aplicacion/AsignarEquiposOrden::ejecutar()` con la MISMA forma de datos
 * (`parametros` compartidos de la tanda + `equipos[]` con sus lotes/turno) —
 * evita repetir el mismo bloque de reglas en los dos Requests.
 *
 * Solo valida FORMA: que cada equipo/lote exista, que las hectáreas sean
 * positivas, que el turno sea uno de los tres valores (sus horas son
 * opcionales, pero si vienen las dos el fin es posterior al inicio), y
 * que Ph y litros por hectárea solo vengan si la orden es de insumo líquido
 * (y kilos por hectárea, solo si es de sólido). La vigencia del
 * equipo, el tope de hectáreas por lote y que la orden esté vigente NO se
 * validan acá: son las guardas de negocio de `AsignarEquiposOrden`.
 */
trait ValidaTandaDeTrabajo
{
    /** @return array<string, mixed> */
    private function reglasParametrosYEquipos(?int $ordenId): array
    {
        return [
            'parametros.humedad_min_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'parametros.humedad_max_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'parametros.viento_max_kmh' => ['nullable', 'numeric', 'gt:0'],
            'parametros.temperatura_max_c' => ['nullable', 'numeric', 'gt:-10', 'lt:60'],
            'parametros.altura_vuelo_m' => ['nullable', 'numeric', 'gt:0'],
            'parametros.velocidad_vuelo_kmh' => ['nullable', 'numeric', 'gt:0'],
            'parametros.ancho_pasada_m' => ['nullable', 'numeric', 'gt:0'],
            'parametros.ph_agua' => ['nullable', 'numeric', 'min:0', 'max:14'],
            'parametros.ph_calda' => ['nullable', 'numeric', 'min:0', 'max:14'],
            'parametros.litros_ha' => ['nullable', 'numeric', 'gt:0'],
            'parametros.kilos_ha' => ['nullable', 'numeric', 'gt:0'],
            'parametros.calda_productos' => ['nullable', 'array'],
            'parametros.calda_productos.*' => ['distinct', Rule::enum(ProductoCalda::class)],
            'parametros.calda' => ['nullable', 'array'],
            'parametros.calda.*.producto' => ['required_with:parametros.calda', 'string', 'max:120'],
            'parametros.calda.*.cantidad' => ['required_with:parametros.calda', 'numeric', 'gt:0'],
            'parametros.calda.*.unidad' => ['required_with:parametros.calda', Rule::in(['l', 'ml', 'kg', 'g'])],

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
            'equipos.*.lotes.*.turno' => ['required', Rule::in(['manana', 'noche', 'todo_el_dia'])],
            // Las horas son una referencia opcional (pedido del dueño, 21/9/2026):
            // lo que se exige es el turno; el horario real lo registra el equipo.
            'equipos.*.lotes.*.turno_hora_inicio' => ['nullable', 'date_format:H:i'],
            'equipos.*.lotes.*.turno_hora_fin' => ['nullable', 'date_format:H:i'],
        ];
    }

    /**
     * Cross-checks comunes: rango de humedad de `parametros`, horario de
     * turno de cada lote, y Ph/calda solo si `$orden` es de insumo líquido.
     * `$orden` puede ser `null` (el Request no pudo resolverla todavía —
     * `CrearOrdenTrabajoRequest` valida `orden_id` en la misma pasada) — en
     * ese caso se omite el chequeo de líquido/sólido, ya cubierto por el
     * error de `orden_id` en sí.
     */
    private function validarParametrosYEquipos(Validator $validator, ?OrdenAplicacion $orden): void
    {
        $parametros = (array) $this->input('parametros', []);

        $humedadMin = $parametros['humedad_min_pct'] ?? null;
        $humedadMax = $parametros['humedad_max_pct'] ?? null;

        if ($humedadMin !== null && $humedadMin !== '' && $humedadMax !== null && $humedadMax !== '' && (float) $humedadMin > (float) $humedadMax) {
            $validator->errors()->add('parametros.humedad_min_pct', __('operaciones.asignacion_equipos.error_humedad_rango'));
        }

        foreach ((array) $this->input('equipos', []) as $indiceEquipo => $equipo) {
            foreach ((array) ($equipo['lotes'] ?? []) as $indiceLote => $lote) {
                $inicio = $lote['turno_hora_inicio'] ?? null;
                $fin = $lote['turno_hora_fin'] ?? null;

                if ($inicio !== null && $fin !== null && $inicio !== '' && $fin !== '' && $fin <= $inicio) {
                    $validator->errors()->add(
                        "equipos.{$indiceEquipo}.lotes.{$indiceLote}.turno_hora_fin",
                        __('operaciones.asignacion_equipos.error_turno_hora_rango'),
                    );
                }
            }
        }

        $esLiquido = $orden?->categoriaInsumo?->tipo_insumo === TipoInsumo::Liquido;

        if (! $esLiquido && ($parametros['ph_agua'] ?? null) !== null && $parametros['ph_agua'] !== '') {
            $validator->errors()->add('parametros.ph_agua', __('operaciones.asignacion_equipos.error_ph_solo_liquido'));
        }

        if (! $esLiquido && ($parametros['ph_calda'] ?? null) !== null && $parametros['ph_calda'] !== '') {
            $validator->errors()->add('parametros.ph_calda', __('operaciones.asignacion_equipos.error_ph_solo_liquido'));
        }

        // Litros por hectárea es de insumo líquido; kilos por hectárea, de sólido.
        if (! $esLiquido && ($parametros['litros_ha'] ?? null) !== null && $parametros['litros_ha'] !== '') {
            $validator->errors()->add('parametros.litros_ha', __('operaciones.ordenes_trabajo.error_litros_solo_liquido'));
        }

        if ($esLiquido && ($parametros['kilos_ha'] ?? null) !== null && $parametros['kilos_ha'] !== '') {
            $validator->errors()->add('parametros.kilos_ha', __('operaciones.ordenes_trabajo.error_kilos_solo_solido'));
        }
    }
}
