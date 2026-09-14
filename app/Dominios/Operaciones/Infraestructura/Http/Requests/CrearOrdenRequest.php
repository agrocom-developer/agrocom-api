<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Requests;

use App\Dominios\Operaciones\Dominio\TipoAplicacion;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/ordenes` (HU-25, tarea 38; `lotes[]` reemplaza `lote_id` por
 * HU-92, tarea 107: una orden cubre varios lotes de la propiedad). La
 * autorización (permiso `operaciones.orden.crear`) se verifica en el
 * controlador, contra el rol activo — no acá, mismo criterio que
 * `CrearContratoRequest`.
 *
 * Los rangos numéricos replican, uno a uno, los `CHECK` de
 * `database/migrations/2026_08_26_100007_create_ope_ordenes_aplicacion_table.php`
 * que corresponden a un campo del formulario (`estado` no es input: lo fija
 * la máquina de estados, ver `Aplicacion/CrearOrden`) — así el usuario ve un
 * error de validación de Laravel, nunca el `QueryException` crudo de
 * Postgres. La unicidad de "orden vigente por lote" no se valida acá: toda
 * orden nace `emitida` (nunca `vigente`), así que esa guarda no aplica al
 * alta — la ejercita `Aplicacion/MaquinaEstados/MaquinaEstadosOrden::activar()`.
 *
 * `contrato_id`/`emitida_por_contacto_id`/`lotes.*.lote_id` se validan por
 * `exists:` contra la tabla física, sin importar el modelo Eloquent de
 * `Comercial` (ADR 0003, regla 3, mismo criterio que el resto del módulo).
 * Deliberadamente NO se exige que el contrato esté `vigente`: la redacción
 * de la HU ("para que las órdenes cuelguen de un contrato vigente") es en
 * realidad el criterio de aceptación de HU-23 sobre el propio contrato, no
 * una regla de esta pantalla — un contrato `borrador` puede necesitar
 * órdenes cargadas de antemano para activarse con todo listo.
 *
 * `lotes` no acepta un lote repetido (`distinct`) y cada
 * `hectareas_solicitadas` no puede superar `com_lotes.hectareas` de ESE
 * lote (chequeado en `withValidator()`, contra la tabla física — mismo
 * criterio ADR 0003 regla 3): pedir más de lo que el lote tiene no es un
 * error de forma que un `numeric`/`gt:0` alcance a cubrir.
 *
 * `tipo_aplicacion` (HU-47, tarea 70) es `required` en el formulario del
 * panel — a diferencia de la columna, que trae `DEFAULT 'desarrollo'` para
 * cualquier alta que no pase por acá (p. ej. un `OrdenAplicacion::create()`
 * directo) — porque acá el usuario elige a propósito, no por omisión.
 */
final class CrearOrdenRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'contrato_id' => ['required', 'integer', Rule::exists('com_contratos', 'id')->whereNull('deleted_at')],
            'lotes' => ['required', 'array', 'min:1'],
            'lotes.*.lote_id' => ['required', 'integer', 'distinct', Rule::exists('com_lotes', 'id')->whereNull('deleted_at')],
            'lotes.*.hectareas_solicitadas' => ['required', 'numeric', 'gt:0'],
            'cantidad_equipos_necesarios' => ['required', 'integer', 'min:1'],
            'nro_aplicacion' => ['required', 'integer', 'min:1'],
            'tipo_aplicacion' => ['required', Rule::enum(TipoAplicacion::class)],
            'litros_ha' => ['required', 'numeric', 'gt:0'],
            'humedad_min_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'humedad_max_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'viento_max_kmh' => ['nullable', 'numeric', 'gt:0'],
            'temperatura_max_c' => ['nullable', 'numeric', 'gt:-10', 'lt:60'],
            'velocidad_max_kmh' => ['nullable', 'numeric', 'gt:0'],
            'altura_vuelo_m' => ['nullable', 'numeric', 'gt:0'],
            'velocidad_vuelo_kmh' => ['nullable', 'numeric', 'gt:0'],
            'ancho_pasada_m' => ['nullable', 'numeric', 'gt:0'],
            'observaciones' => ['nullable', 'string'],
            'emitida_por_contacto_id' => ['nullable', 'integer', Rule::exists('com_cliente_contactos', 'id')->whereNull('deleted_at')],
            'fecha_emision' => ['required', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $minimo = $this->input('humedad_min_pct');
            $maximo = $this->input('humedad_max_pct');

            if ($minimo !== null && $minimo !== '' && $maximo !== null && $maximo !== '' && (float) $minimo > (float) $maximo) {
                $validator->errors()->add('humedad_min_pct', __('operaciones.ordenes.error_humedad_rango'));
            }

            foreach ((array) $this->input('lotes', []) as $indice => $lote) {
                $loteId = $lote['lote_id'] ?? null;
                $hectareasSolicitadas = $lote['hectareas_solicitadas'] ?? null;

                if ($loteId === null || $loteId === '' || $hectareasSolicitadas === null || $hectareasSolicitadas === '') {
                    continue;
                }

                $hectareasLote = DB::table('com_lotes')->where('id', $loteId)->value('hectareas');

                if ($hectareasLote !== null && BigDecimal::of((string) $hectareasSolicitadas)->isGreaterThan(BigDecimal::of((string) $hectareasLote))) {
                    $validator->errors()->add(
                        "lotes.{$indice}.hectareas_solicitadas",
                        __('operaciones.ordenes.error_hectareas_solicitadas_superan_lote'),
                    );
                }
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'contrato_id.required' => __('operaciones.ordenes.error_contrato_requerido'),
            'contrato_id.exists' => __('operaciones.ordenes.error_contrato_invalido'),
            'lotes.required' => __('operaciones.ordenes.error_lotes_requerido'),
            'lotes.*.lote_id.required' => __('operaciones.ordenes.error_lote_requerido'),
            'lotes.*.lote_id.exists' => __('operaciones.ordenes.error_lote_invalido'),
            'lotes.*.lote_id.distinct' => __('operaciones.ordenes.error_lote_repetido'),
            'emitida_por_contacto_id.exists' => __('operaciones.ordenes.error_contacto_invalido'),
        ];
    }
}
