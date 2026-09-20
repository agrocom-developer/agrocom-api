<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Requests;

use App\Dominios\Operaciones\Dominio\CausaPausa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * `POST /panel/pausas` (HU-44, tarea 58). La autorización (permiso
 * `operaciones.pausa.registrar`) se verifica en el controlador, contra el
 * rol activo — no acá, mismo criterio que `CrearGastoRequest`.
 *
 * `causa` valida contra el catálogo cerrado {@see CausaPausa} — así un
 * intento con una causa fuera de catálogo recibe un 422 estándar de Laravel,
 * nunca el `QueryException` crudo del `CHECK` de Postgres (que además SQLite
 * no reproduce en la suite local).
 *
 * El formulario pide una fecha y un rango horario (`atoms/date` +
 * `atoms/time-range`, tarea 113): `fecha` (`Y-m-d`) y las dos horas
 * (`H:i`, 24 h). {@see self::inicio()} y {@see self::fin()} las juntan en los
 * textos `Y-m-d H:i` que espera `Aplicacion/RegistrarPausa`, sin offset de
 * huso: el caso de uso los interpreta con la zona de la aplicación, igual que
 * antes con el selector de fecha y hora. Una pausa queda dentro de un solo día.
 *
 * `fin > inicio` NO se valida acá: la comparación real la hace
 * `Aplicacion/RegistrarPausa` sobre los valores ya normalizados a UTC (mismo
 * cuidado que `ope_sesiones`).
 */
final class RegistrarPausaRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'sesion_id' => ['required', 'integer', Rule::exists('ope_sesiones', 'id')->whereNull('deleted_at')],
            'causa' => ['required', new Enum(CausaPausa::class)],
            'fecha' => ['required', 'date_format:Y-m-d'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin' => ['required', 'date_format:H:i'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'sesion_id.required' => __('operaciones.pausas.error_sesion_requerida'),
            'sesion_id.exists' => __('operaciones.pausas.error_sesion_invalida'),
            'causa.required' => __('operaciones.pausas.error_causa_requerida'),
            'causa.enum' => __('operaciones.pausas.error_causa_invalida'),
            'fecha.required' => __('operaciones.pausas.error_fecha_requerida'),
            'fecha.date_format' => __('operaciones.pausas.error_fecha_invalida'),
            'hora_inicio.required' => __('operaciones.pausas.error_inicio_requerido'),
            'hora_inicio.date_format' => __('operaciones.pausas.error_hora_invalida'),
            'hora_fin.required' => __('operaciones.pausas.error_fin_requerido'),
            'hora_fin.date_format' => __('operaciones.pausas.error_hora_invalida'),
        ];
    }

    /** Inicio de la pausa como `Y-m-d H:i`, ya validado. */
    public function inicio(): string
    {
        return $this->validated('fecha').' '.$this->validated('hora_inicio');
    }

    /** Fin de la pausa como `Y-m-d H:i`, ya validado. */
    public function fin(): string
    {
        return $this->validated('fecha').' '.$this->validated('hora_fin');
    }
}
