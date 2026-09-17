<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Requests;

use App\Dominios\Comercial\Dominio\CicloVidaCultivo;
use App\Dominios\Comercial\Dominio\TipoCultivo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/cultivos` (HU-48, tarea 71). La autorización (permiso
 * `comercial.cultivo.crear`) se verifica en el controlador, contra el rol
 * activo — no acá, mismo criterio que el resto del panel.
 *
 * `tipo_cultivo`/`ciclo_vida` `required` (ampliación 16/9/2026): un cultivo
 * nuevo entra clasificado desde el día uno — el catálogo demo previo a esta
 * fecha se completa por el seeder, no por dejar el campo opcional acá.
 */
final class CrearCultivoRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nombre_comun' => ['required', 'string', 'max:80'],
            'nombre_cientifico' => ['nullable', 'string', 'max:150'],
            'tipo_cultivo' => ['required', Rule::enum(TipoCultivo::class)],
            'ciclo_vida' => ['required', Rule::enum(CicloVidaCultivo::class)],
            'notas_agronomicas' => ['nullable', 'string', 'max:2000'],
            'activo' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'nombre_comun.required' => __('comercial.cultivos.error_nombre_comun_requerido'),
            'tipo_cultivo.required' => __('comercial.validacion.tipo_cultivo_requerido'),
            'tipo_cultivo.enum' => __('comercial.validacion.tipo_cultivo_invalido'),
            'ciclo_vida.required' => __('comercial.validacion.ciclo_vida_requerido'),
            'ciclo_vida.enum' => __('comercial.validacion.ciclo_vida_invalido'),
        ];
    }
}
