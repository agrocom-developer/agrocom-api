<?php

namespace App\Dominios\Solicitudes\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Cuerpo de `POST /api/terrenos` y `PUT /api/terrenos/{id}` (especificación §10.1). */
final class CrearTerrenoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120'],
            // Hectáreas como decimal exacto: se acepta número o cadena, nunca se opera como float.
            'hectareas' => ['required', 'numeric', 'gt:0', 'max:99999999', 'decimal:0,4'],
            'cultivo' => ['nullable', 'string', 'max:80'],
            'geometria' => ['nullable', 'array'],
            'geometria.type' => ['required_with:geometria', Rule::in(['Polygon', 'MultiPolygon'])],
            'geometria.coordinates' => ['required_with:geometria', 'array', 'min:1'],
            'referencias_acceso' => ['nullable', 'string', 'max:1000'],
            'colindantes_sensibles' => ['nullable', 'string', 'max:1000'],
            'acceso_dificil' => ['nullable', 'boolean'],
            'zona_id' => ['nullable', 'integer', Rule::exists('sol_zonas', 'id')->whereNull('deleted_at')],
            // Ubicación (ADR 0045, punto 3): departamento y provincia del catálogo oficial, la provincia
            // del departamento elegido; municipio y comunidad son texto de referencia. Se exigen al publicar.
            'departamento_codigo' => ['nullable', 'string', 'required_with:provincia_codigo', Rule::exists('sol_departamentos', 'codigo')->whereNull('deleted_at')],
            'provincia_codigo' => [
                'nullable',
                'string',
                Rule::exists('sol_provincias', 'codigo')
                    ->where('departamento_codigo', (string) $this->input('departamento_codigo'))
                    ->whereNull('deleted_at'),
            ],
            'municipio' => ['nullable', 'string', 'max:120'],
            'comunidad' => ['nullable', 'string', 'max:120'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('solicitudes.campos');
    }
}
