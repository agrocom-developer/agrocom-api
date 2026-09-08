<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Requests;

use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `PUT /panel/contratos/{contrato}` (HU-23, tarea 34). Mismo criterio que
 * `CrearContratoRequest` para los rangos de `CHECK` y para que `ventanas` sea
 * opcional (HU-47, tarea 70) — ver su docblock.
 *
 * `ventanas.*.id`, cuando viene, tiene que pertenecer AL PROPIO contrato que
 * se está editando — nunca a otro (mismo espíritu que
 * `ActualizarClienteRequest` con `contactos.*.id`, invariante 5 de CLAUDE.md
 * aplicada acá al panel interno).
 */
final class ActualizarContratoRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Contrato|null $contrato */
        $contrato = $this->route('contrato');
        $contratoId = $contrato?->id;

        return [
            'cliente_id' => ['required', 'integer', Rule::exists('com_clientes', 'id')->whereNull('deleted_at')],
            'campania_id' => ['required', 'integer', Rule::exists('cpn_campanias', 'id')->whereNull('deleted_at')],
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
            'altura_vuelo_m' => ['nullable', 'numeric', 'gt:0'],
            'ventanas' => ['nullable', 'array'],
            'ventanas.*.id' => [
                'nullable',
                'integer',
                Rule::exists('com_contrato_ventanas', 'id')
                    ->where('contrato_id', $contratoId)
                    ->whereNull('deleted_at'),
            ],
            'ventanas.*.hora_inicio' => ['nullable', 'required_with:ventanas.*.hora_fin', 'date_format:H:i'],
            'ventanas.*.hora_fin' => ['nullable', 'required_with:ventanas.*.hora_inicio', 'date_format:H:i', 'after:ventanas.*.hora_inicio'],
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
            'campania_id.required' => __('comercial.contratos.error_campania_requerida'),
            'campania_id.exists' => __('comercial.contratos.error_campania_invalida'),
            'ventanas.*.id.exists' => __('comercial.contratos.error_ventana_ajena'),
            'ventanas.*.hora_inicio.required_with' => __('comercial.contratos.error_ventana_incompleta'),
            'ventanas.*.hora_fin.required_with' => __('comercial.contratos.error_ventana_incompleta'),
            'ventanas.*.hora_fin.after' => __('comercial.contratos.error_ventana_horas'),
        ];
    }
}
