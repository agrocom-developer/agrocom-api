<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Requests;

use App\Dominios\Operaciones\Dominio\TipoAplicacion;
use App\Dominios\Operaciones\Dominio\TipoInsumo;
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
 * Los 8 campos de límites climáticos y parámetros de vuelo (humedad, viento,
 * temperatura, velocidad, altura de vuelo, ancho de pasada) YA NO se piden
 * acá: se movieron a `Trabajo` (migración
 * `2026_09_18_100001_mueve_clima_vuelo_de_ordenes_a_trabajos_table`) porque
 * describen el vuelo de cada equipo, no el pedido — se cargan por equipo en
 * `AsignarEquipoOrdenRequest`, al confirmar la asignación.
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
 * Cada lote también debe ser de la MISMA propiedad/cliente que el contrato
 * elegido (`withValidator()`, vía `com_lotes.propiedad_id` →
 * `com_propiedades.cliente_id`): el contrato es el QUIÉN, la orden es el
 * CÓMO — no tiene sentido de negocio un contrato del cliente A con un lote
 * del cliente B. El formulario del panel ya filtra el `<select>` de lote por
 * el cliente del contrato elegido (JS), pero esa es presentación — acá es la
 * guarda real.
 *
 * `tipo_aplicacion` (HU-47, tarea 70) es `required` en el formulario del
 * panel — a diferencia de la columna, que trae `DEFAULT 'desarrollo'` para
 * cualquier alta que no pase por acá (p. ej. un `OrdenAplicacion::create()`
 * directo) — porque acá el usuario elige a propósito, no por omisión.
 *
 * `categoria_insumo_id` (HU-79, tarea 110) es `required` acá aunque la
 * columna sea nullable: no hay valor de origen del que inferirla para
 * órdenes ya cargadas (ver docblock de la migración), pero toda orden nueva
 * del panel sí la elige a propósito. `litros_ha`/`kilos_por_vuelo` son
 * `nullable` en `rules()` porque cuál de los dos hace falta depende del
 * `tipo_insumo` de la categoría elegida, no de la forma del campo — esa
 * exigencia cruzada vive en `withValidator()`.
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
            'categoria_insumo_id' => ['required', 'integer', Rule::exists('ope_categorias_insumo', 'id')->whereNull('deleted_at')],
            'litros_ha' => ['nullable', 'numeric', 'gt:0'],
            'kilos_por_vuelo' => ['nullable', 'numeric', 'gt:0'],
            'observaciones' => ['nullable', 'string'],
            'emitida_por_contacto_id' => ['nullable', 'integer', Rule::exists('com_cliente_contactos', 'id')->whereNull('deleted_at')],
            'fecha_emision' => ['required', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validarCampoSegunCategoriaInsumo($validator);

            $contratoId = $this->input('contrato_id');
            $clienteDelContrato = $contratoId !== null && $contratoId !== ''
                ? DB::table('com_contratos')->where('id', $contratoId)->value('cliente_id')
                : null;

            foreach ((array) $this->input('lotes', []) as $indice => $lote) {
                $loteId = $lote['lote_id'] ?? null;
                $hectareasSolicitadas = $lote['hectareas_solicitadas'] ?? null;

                if ($loteId === null || $loteId === '') {
                    continue;
                }

                // Una sola consulta para hectáreas y cliente (vía
                // propiedad_id → cliente_id): consistencia de negocio, el
                // contrato es el QUIÉN, la orden es el CÓMO — no se arma una
                // orden del contrato del cliente A con un lote del cliente B
                // (mismo criterio que invariante 5, aplicado acá al panel
                // interno, no al portal del cliente).
                $filaLote = DB::table('com_lotes as l')
                    ->join('com_propiedades as p', 'p.id', '=', 'l.propiedad_id')
                    ->where('l.id', $loteId)
                    ->first(['l.hectareas', 'p.cliente_id']);

                if ($filaLote === null) {
                    continue;
                }

                if ($clienteDelContrato !== null && (int) $filaLote->cliente_id !== (int) $clienteDelContrato) {
                    $validator->errors()->add(
                        "lotes.{$indice}.lote_id",
                        __('operaciones.ordenes.error_lote_de_otro_cliente'),
                    );
                }

                if ($hectareasSolicitadas === null || $hectareasSolicitadas === '') {
                    continue;
                }

                if (BigDecimal::of((string) $hectareasSolicitadas)->isGreaterThan(BigDecimal::of((string) $filaLote->hectareas))) {
                    $validator->errors()->add(
                        "lotes.{$indice}.hectareas_solicitadas",
                        __('operaciones.ordenes.error_hectareas_solicitadas_superan_lote'),
                    );
                }
            }
        });
    }

    /**
     * HU-79 (tarea 110): el campo que la orden REALMENTE exige depende del
     * `tipo_insumo` de la categoría elegida — sólido pide `kilos_por_vuelo`,
     * líquido pide `litros_ha`. Cruza `ope_categorias_insumo` (no es un
     * `Rule::requiredIf` estático porque depende de un valor de OTRA tabla,
     * resuelto recién acá) — mismo criterio que la validación de hectáreas
     * por lote, unas líneas más abajo. Si `categoria_insumo_id` ya falló su
     * propio `exists`, no hay categoría que resolver: no se agrega un
     * segundo error encima del que ya puso `rules()`.
     */
    private function validarCampoSegunCategoriaInsumo(Validator $validator): void
    {
        $categoriaInsumoId = $this->input('categoria_insumo_id');

        if ($categoriaInsumoId === null || $categoriaInsumoId === '') {
            return;
        }

        $tipoInsumo = DB::table('ope_categorias_insumo')->where('id', $categoriaInsumoId)->value('tipo_insumo');

        if ($tipoInsumo === TipoInsumo::Solido->value) {
            $kilosPorVuelo = $this->input('kilos_por_vuelo');

            if ($kilosPorVuelo === null || $kilosPorVuelo === '') {
                $validator->errors()->add('kilos_por_vuelo', __('operaciones.ordenes.error_kilos_por_vuelo_requerido'));
            }
        } elseif ($tipoInsumo === TipoInsumo::Liquido->value) {
            $litrosHa = $this->input('litros_ha');

            if ($litrosHa === null || $litrosHa === '') {
                $validator->errors()->add('litros_ha', __('operaciones.ordenes.error_litros_ha_requerido'));
            }
        }
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
            'lotes.*.hectareas_solicitadas.required' => __('operaciones.ordenes.error_hectareas_solicitadas_requeridas'),
            'cantidad_equipos_necesarios.required' => __('operaciones.ordenes.error_cantidad_equipos_requerida'),
            'nro_aplicacion.required' => __('operaciones.ordenes.error_nro_aplicacion_requerido'),
            'tipo_aplicacion.required' => __('operaciones.ordenes.error_tipo_aplicacion_requerido'),
            'fecha_emision.required' => __('operaciones.ordenes.error_fecha_emision_requerida'),
            'categoria_insumo_id.required' => __('operaciones.ordenes.error_categoria_insumo_requerida'),
            'categoria_insumo_id.exists' => __('operaciones.ordenes.error_categoria_insumo_invalida'),
            'emitida_por_contacto_id.exists' => __('operaciones.ordenes.error_contacto_invalido'),
        ];
    }
}
