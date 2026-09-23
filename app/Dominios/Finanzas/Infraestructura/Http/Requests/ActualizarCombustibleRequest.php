<?php

namespace App\Dominios\Finanzas\Infraestructura\Http\Requests;

use App\Dominios\Finanzas\Infraestructura\Http\Requests\Concerns\ReglasCombustible;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `PUT /panel/combustible/{combustible}` (tarea 134). Mismas reglas de forma
 * que `CrearCombustibleRequest` (`Concerns/ReglasCombustible`). La
 * autorización (permiso `finanzas.combustible.eliminar`, reusado para gatear
 * la edición: no existe `.editar` en el catálogo) se verifica en el
 * controlador.
 *
 * Que el recurso elegido siguiera asignado al equipo en la fecha corregida
 * no se valida acá: vive en `Aplicacion/ActualizarCombustible`, mismo
 * criterio que `CrearCombustibleRequest`.
 */
final class ActualizarCombustibleRequest extends FormRequest
{
    use ReglasCombustible;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->reglasCombustible();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return $this->mensajesCombustible();
    }
}
