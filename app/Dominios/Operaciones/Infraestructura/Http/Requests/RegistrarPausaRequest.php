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
 * `fin > inicio` NO se valida acá con `after:inicio`: ambos campos vienen con
 * su propio offset de huso horario (el mismo cuidado que `ope_sesiones`), así
 * que la comparación real la hace `Aplicacion/RegistrarPausa` sobre los
 * valores ya normalizados a UTC — duplicarla acá con datetime strings crudos
 * arriesgaría un falso negativo con offsets distintos.
 */
final class RegistrarPausaRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'sesion_id' => ['required', 'integer', Rule::exists('ope_sesiones', 'id')->whereNull('deleted_at')],
            'causa' => ['required', new Enum(CausaPausa::class)],
            'inicio' => ['required', 'date'],
            'fin' => ['required', 'date'],
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
        ];
    }
}
