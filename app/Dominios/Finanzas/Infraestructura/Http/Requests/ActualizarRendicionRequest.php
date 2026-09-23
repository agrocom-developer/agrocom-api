<?php

namespace App\Dominios\Finanzas\Infraestructura\Http\Requests;

use App\Dominios\Finanzas\Infraestructura\Http\Requests\Concerns\ReglasRendicion;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `PUT /panel/rendiciones/{rendicion}` (tarea 134). Mismas reglas de forma
 * que `CrearRendicionRequest` (`Concerns/ReglasRendicion`) — solo cabecera,
 * `estado`/`monto`/`aprobado_por` no viajan acá (invariante 7). La
 * autorización (permiso `finanzas.rendicion.presentar`, reusado para gatear
 * la edición: no existe `.editar` en el catálogo) se verifica en el
 * controlador.
 *
 * Que la rendición siga `Abierta` no se valida acá: es una regla de ESTADO,
 * no de forma, y vive en `Aplicacion/ActualizarRendicion` vía
 * `Dominio/PoliticaEdicionRendicion` (invariante 7).
 */
final class ActualizarRendicionRequest extends FormRequest
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
