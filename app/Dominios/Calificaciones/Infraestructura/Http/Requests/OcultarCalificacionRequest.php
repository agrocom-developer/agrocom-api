<?php

namespace App\Dominios\Calificaciones\Infraestructura\Http\Requests;

use App\Dominios\Calificaciones\Dominio\CausaModeracion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Formulario del panel para ocultar una calificación: causa del catálogo y motivo. */
final class OcultarCalificacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'causa' => ['required', 'string', Rule::enum(CausaModeracion::class)],
            'motivo' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('calificaciones.campos');
    }
}
