<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Requests;

use App\Dominios\Comercial\Dominio\EtapaCultivo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * `POST /panel/propiedades/{propiedad}/siembra` (HU-48, tarea 71, etapa 3;
 * ruta y permiso renombrados por ADR 0020). La autorización (permiso
 * `comercial.propiedad.editar`) se verifica en el controlador, contra el rol
 * activo — no acá.
 *
 * La siembra se carga por SECTORES (21/9/2026, pedido directo: una fila con
 * cinco campos por lote no sirve para una propiedad de mil lotes): un sector
 * es un cultivo, su etapa y sus fechas, más los lotes que lo comparten. El
 * controlador lo expande a una fila por lote para `GuardarSiembraCampania`.
 *
 * `sectores.*.lotes` viaja como UNA cadena de ids separados por coma y no
 * como un arreglo: mil lotes serían mil campos, y PHP corta el formulario en
 * `max_input_vars` (1000 por defecto) sin avisar.
 *
 * `sectores` puede venir vacío: quitar todos los sectores y guardar es la
 * forma de dejar la propiedad sin siembra en esa campaña. Lo que no se admite
 * es un sector a medias (sin cultivo o sin lotes), ni un lote en dos sectores,
 * ni un lote de otra propiedad.
 */
final class GuardarSiembraRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'campania_id' => [
                'required',
                'integer',
                Rule::exists('cpn_campanias', 'id')->whereNull('deleted_at'),
            ],
            'sectores' => ['nullable', 'array'],
            'sectores.*.cultivo_id' => [
                'required',
                'integer',
                Rule::exists('com_cultivos', 'id')->whereNull('deleted_at'),
            ],
            'sectores.*.etapa_cultivo' => ['nullable', Rule::enum(EtapaCultivo::class)],
            'sectores.*.fecha_siembra' => ['nullable', 'date'],
            'sectores.*.fecha_cosecha_estimada' => ['nullable', 'date', 'after_or_equal:sectores.*.fecha_siembra'],
            'sectores.*.lotes' => ['required', 'string', 'regex:/^\d+(,\d+)*$/'],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validador): void {
            if ($validador->errors()->isNotEmpty()) {
                return;
            }

            $propiedad = $this->route('propiedad');
            $lotesDeLaPropiedad = $propiedad instanceof Propiedad
                ? $propiedad->lotes()->pluck('id')->map(fn ($id): int => (int) $id)->flip()
                : collect();
            $vistos = [];

            foreach ((array) $this->input('sectores', []) as $indice => $sector) {
                foreach (self::idsDeLotes($sector['lotes'] ?? '') as $loteId) {
                    if (! $lotesDeLaPropiedad->has($loteId)) {
                        $validador->errors()->add("sectores.{$indice}.lotes", __('comercial.validacion.siembra_lote_ajeno'));

                        continue 2;
                    }

                    if (isset($vistos[$loteId])) {
                        $validador->errors()->add("sectores.{$indice}.lotes", __('comercial.validacion.siembra_lote_repetido'));

                        continue 2;
                    }

                    $vistos[$loteId] = true;
                }
            }
        }];
    }

    /**
     * "3,7,12" → [3, 7, 12].
     *
     * @return list<int>
     */
    public static function idsDeLotes(mixed $cadena): array
    {
        return array_values(array_map(intval(...), array_filter(explode(',', (string) $cadena), fn (string $id): bool => $id !== '')));
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'campania_id.required' => __('comercial.validacion.siembra_campania_requerida'),
            'campania_id.exists' => __('comercial.contratos.error_campania_invalida'),
            'sectores.*.cultivo_id.required' => __('comercial.validacion.siembra_cultivo_requerido'),
            'sectores.*.etapa_cultivo.enum' => __('comercial.validacion.siembra_etapa_invalida'),
            'sectores.*.fecha_cosecha_estimada.after_or_equal' => __('comercial.validacion.siembra_cosecha_estimada_invalida'),
            'sectores.*.lotes.required' => __('comercial.validacion.siembra_lotes_requeridos'),
            'sectores.*.lotes.regex' => __('comercial.validacion.siembra_lotes_requeridos'),
        ];
    }
}
