<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/contratos` (HU-23, tarea 34). La autorización (permiso
 * `comercial.contrato.crear`) se verifica en el controlador, contra el rol
 * activo — no acá, mismo criterio que `CrearClienteRequest`.
 *
 * Los rangos replican, uno a uno, los 13 `CHECK` de
 * `database/migrations/2026_08_26_100003_create_com_contratos_table.php`
 * que corresponden a un campo del formulario (los otros dos —`estado` y
 * `monto_total`— no son input: los fija el servicio de dominio, ver
 * `Aplicacion/CrearContrato`) — así el usuario ve un error de validación de
 * Laravel, nunca el `QueryException` crudo de Postgres.
 *
 * `ventanas.*.hora_fin` usa `after:ventanas.*.hora_inicio`: Laravel resuelve
 * el wildcard contra el MISMO índice de fila, replicando el `CHECK
 * (hora_fin > hora_inicio)` de `com_contrato_ventanas`. El solapamiento entre
 * ventanas (que ningún `CHECK` puede expresar) se valida aparte, en
 * `Aplicacion/CrearContrato` vía `ValidadorSolapamientoVentanas`.
 */
final class CrearContratoRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'cliente_id' => ['required', 'integer', Rule::exists('com_clientes', 'id')->whereNull('deleted_at')],
            'hectareas_contratadas' => ['required', 'numeric', 'gt:0'],
            'aplicaciones_previstas' => ['required', 'integer', 'min:1'],
            'precio_ha' => ['required', 'numeric', 'min:0'],
            'adelanto_monto' => ['nullable', 'numeric', 'min:0'],
            'adelanto_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'viento_max_kmh' => ['nullable', 'numeric', 'gt:0'],
            'temperatura_max_c' => ['nullable', 'numeric', 'gt:-10', 'lt:60'],
            'humedad_min_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'humedad_max_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'velocidad_max_kmh' => ['nullable', 'numeric', 'gt:0'],
            'umbral_reporte_avance_ha' => ['nullable', 'numeric', 'gt:0'],
            'ventanas' => ['required', 'array', 'min:1'],
            'ventanas.*.hora_inicio' => ['required', 'date_format:H:i'],
            'ventanas.*.hora_fin' => ['required', 'date_format:H:i', 'after:ventanas.*.hora_inicio'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $minimo = $this->input('humedad_min_pct');
            $maximo = $this->input('humedad_max_pct');

            if ($minimo !== null && $minimo !== '' && $maximo !== null && $maximo !== '' && (float) $minimo > (float) $maximo) {
                $validator->errors()->add('humedad_min_pct', __('comercial.contratos.error_humedad_rango'));
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'cliente_id.required' => __('comercial.contratos.error_cliente_requerido'),
            'cliente_id.exists' => __('comercial.contratos.error_cliente_invalido'),
            'ventanas.required' => __('comercial.contratos.error_ventanas_minimo'),
            'ventanas.min' => __('comercial.contratos.error_ventanas_minimo'),
            'ventanas.*.hora_fin.after' => __('comercial.contratos.error_ventana_horas'),
        ];
    }
}
