<?php

namespace App\Dominios\Personal\Infraestructura\Http\Requests\Concerns;

use App\Dominios\Personal\Aplicacion\DatosPersona;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use Illuminate\Validation\Rule;

/**
 * Reglas del formulario de persona, compartidas por el alta y la edición
 * (21/9/2026): datos personales, datos de referencia y trabajo en campo.
 *
 * Nombres, apellido paterno, cédula y celular son obligatorios: es lo mínimo
 * para ubicar a quien se le confía un dron. Apellido materno, correo y
 * dirección son opcionales. La cédula acepta complemento y extensión
 * («1234567-1A SC») y es única entre las personas vivas.
 *
 * `base_id` es opcional (una persona sin base asignada es un caso válido) y,
 * cuando viene, apunta a una base viva.
 */
trait ValidaDatosDePersona
{
    /** @return array<string, mixed> */
    protected function reglasDePersona(?int $personaId = null): array
    {
        return [
            'nombres' => ['required', 'string', 'max:80'],
            'apellido_paterno' => ['required', 'string', 'max:80'],
            'apellido_materno' => ['nullable', 'string', 'max:80'],
            'ci' => [
                'required',
                'string',
                'max:20',
                'regex:/^[0-9]{4,10}(-[0-9A-Za-z]{1,2})?( ?[A-Za-z]{2})?$/',
                Rule::unique('per_personas', 'ci')->ignore($personaId)->whereNull('deleted_at'),
            ],
            'celular' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9 ]{7,20}$/'],
            'correo' => ['nullable', 'string', 'email', 'max:150'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'rol' => ['required', Rule::enum(RolOperativoPersona::class)],
            'base_id' => [
                'nullable',
                'integer',
                Rule::exists('per_bases', 'id')->whereNull('deleted_at'),
            ],
            'tarifa_ha' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    protected function mensajesDePersona(): array
    {
        return [
            'nombres.required' => __('personal.personas.error_nombres_requerido'),
            'apellido_paterno.required' => __('personal.personas.error_apellido_paterno_requerido'),
            'ci.required' => __('personal.personas.error_ci_requerido'),
            'ci.regex' => __('personal.personas.error_ci_formato'),
            'ci.unique' => __('personal.personas.error_ci_repetido'),
            'celular.required' => __('personal.personas.error_celular_requerido'),
            'celular.regex' => __('personal.personas.error_celular_formato'),
            'correo.email' => __('personal.personas.error_correo_formato'),
            'rol.required' => __('personal.personas.error_rol_requerido'),
            'base_id.exists' => __('personal.validacion.base_invalida'),
        ];
    }

    /** Lo validado, ya como el objeto que reciben `CrearPersona` y `ActualizarPersona`. */
    public function datosPersona(): DatosPersona
    {
        /** @var array<string, mixed> $datos */
        $datos = $this->validated();

        $texto = fn (string $campo): ?string => isset($datos[$campo]) && trim((string) $datos[$campo]) !== ''
            ? trim((string) $datos[$campo])
            : null;

        return new DatosPersona(
            nombres: (string) $texto('nombres'),
            apellidoPaterno: (string) $texto('apellido_paterno'),
            apellidoMaterno: $texto('apellido_materno'),
            ci: mb_strtoupper((string) $texto('ci')),
            celular: (string) $texto('celular'),
            correo: $texto('correo') !== null ? mb_strtolower($texto('correo')) : null,
            direccion: $texto('direccion'),
            rol: RolOperativoPersona::from((string) $datos['rol']),
            baseId: isset($datos['base_id']) && $datos['base_id'] !== '' ? (int) $datos['base_id'] : null,
            tarifaHa: $texto('tarifa_ha'),
        );
    }
}
