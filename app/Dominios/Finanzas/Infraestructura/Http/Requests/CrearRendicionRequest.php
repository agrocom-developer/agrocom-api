<?php

namespace App\Dominios\Finanzas\Infraestructura\Http\Requests;

use App\Dominios\Finanzas\Infraestructura\Http\Requests\Concerns\ReglasRendicion;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /panel/rendiciones` (HU-34, tarea 48). La autorización (permiso
 * `finanzas.rendicion.crear`) se verifica en el controlador, contra el rol
 * activo — no acá, mismo criterio que `CrearGastoRequest`.
 *
 * Reglas en `Concerns/ReglasRendicion` (tarea 134): compartidas con
 * `ActualizarRendicionRequest`, la edición de cabecera no cambia ni un campo
 * respecto del alta.
 *
 * `base_id`/`jefe_campo_id` se validan por `exists:` contra las tablas
 * físicas, sin importar los modelos Eloquent de `Personal` (ADR 0003 regla
 * 3, mismo criterio que `CrearGastoRequest`/`CrearAnticipoRequest`).
 */
final class CrearRendicionRequest extends FormRequest
{
    use ReglasRendicion;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->reglasRendicion();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return $this->mensajesRendicion();
    }
}
