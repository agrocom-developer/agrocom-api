<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Requests\Concerns;

use App\Dominios\Operaciones\Dominio\ProductoCalda;
use App\Dominios\Operaciones\Dominio\TipoInsumo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * Reglas de las INDICACIONES compartidas de una tanda —calda, límites
 * climáticos, parámetros de vuelo—, extraídas de {@see ValidaTandaDeTrabajo}
 * (tarea 127) para que `CrearOrdenTrabajoRequest` (alta, `parametros` +
 * `equipos[]`) y `ActualizarOrdenTrabajoRequest` (edición, solo la cabecera)
 * compartan la MISMA validación sin copiarla: la edición no toca el reparto,
 * así que no necesita las reglas de `equipos.*.lotes.*`.
 *
 * Solo valida FORMA: rangos numéricos y que Ph/litros por hectárea solo
 * vengan si la orden es de insumo líquido (y kilos por hectárea, solo si es
 * de sólido). La vigencia de la orden y del equipo, y el tope de hectáreas
 * por lote, son guardas de negocio de `Aplicacion/`, no de acá.
 */
trait ValidaIndicacionesOrdenTrabajo
{
    /** @return array<string, mixed> */
    private function reglasIndicaciones(): array
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
        ];
    }

    /**
     * Cross-checks: rango de humedad, y Ph/litros/kilos solo si `$orden` es
     * del tipo de insumo que corresponde. `$orden` puede ser `null` (el
     * Request no pudo resolverla todavía) — ahí se omite el chequeo de
     * líquido/sólido.
     */
    private function validarIndicaciones(Validator $validator, ?OrdenAplicacion $orden): void
    {
        $parametros = (array) $this->input('parametros', []);

        $humedadMin = $parametros['humedad_min_pct'] ?? null;
        $humedadMax = $parametros['humedad_max_pct'] ?? null;

        if ($humedadMin !== null && $humedadMin !== '' && $humedadMax !== null && $humedadMax !== '' && (float) $humedadMin > (float) $humedadMax) {
            $validator->errors()->add('parametros.humedad_min_pct', __('operaciones.asignacion_equipos.error_humedad_rango'));
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
