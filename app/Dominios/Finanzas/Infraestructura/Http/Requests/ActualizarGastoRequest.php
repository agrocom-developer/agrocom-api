<?php

namespace App\Dominios\Finanzas\Infraestructura\Http\Requests;

use App\Dominios\Finanzas\Infraestructura\Http\Requests\Concerns\ReglasGasto;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `PUT /panel/gastos/{gasto}` (tarea 134). Mismas reglas de forma que
 * `CrearGastoRequest` (`Concerns/ReglasGasto`) — la edición no cambia ni un
 * campo respecto del alta. La autorización (permiso `finanzas.gasto.eliminar`,
 * reusado para gatear la edición: no existe `.editar` en el catálogo) se
 * verifica en el controlador.
 *
 * Que el gasto siga siendo editable (su rendición, si tiene una, sigue
 * `Abierta`) no se valida acá: es una regla de ESTADO ajeno, no de forma, y
 * vive en `Aplicacion/ActualizarGasto` vía `Dominio/PoliticaEdicionGasto`
 * (invariante 7).
 */
final class ActualizarGastoRequest extends FormRequest
{
    use ReglasGasto;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->reglasGasto();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return $this->mensajesGasto();
    }
}
