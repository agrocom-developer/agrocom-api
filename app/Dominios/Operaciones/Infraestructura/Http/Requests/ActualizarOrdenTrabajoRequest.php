<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Requests;

use App\Dominios\Operaciones\Dominio\EstadoTableroTrabajo;
use App\Dominios\Operaciones\Dominio\PoliticaEdicionOrdenTrabajo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenTrabajo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Operaciones\Infraestructura\Http\Requests\Concerns\ValidaIndicacionesOrdenTrabajo;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `PUT /panel/trabajos/{ordenTrabajo}` (tarea 127): edición de la cabecera de
 * una Orden de Trabajo —indicaciones compartidas, mismas reglas que el alta
 * vía {@see ValidaIndicacionesOrdenTrabajo}—. El reparto (equipos, lotes,
 * hectáreas, turno) NO se edita acá, así que no hay reglas de
 * `equipos.*.lotes.*`.
 *
 * Que la Orden de Trabajo siga siendo editable NO se valida acá: es una regla
 * de ESTADO, no de forma, y vive en `Aplicacion/ActualizarOrdenTrabajo`
 * (`PoliticaEdicionOrdenTrabajo`, invariante 7 de CLAUDE.md).
 */
final class ActualizarOrdenTrabajoRequest extends FormRequest
{
    use ValidaIndicacionesOrdenTrabajo;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->reglasIndicaciones(),
            'motivo_correccion' => [Rule::requiredIf($this->exigeMotivo()), 'nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validarIndicaciones($validator, $this->ordenTrabajo()->orden);
        });
    }

    /** Una tanda con algún trabajo ya `cerrado` pide motivo ({@see PoliticaEdicionOrdenTrabajo}). */
    private function exigeMotivo(): bool
    {
        return PoliticaEdicionOrdenTrabajo::exigeMotivo($this->estadosTrabajos());
    }

    /** @return list<EstadoTableroTrabajo> */
    private function estadosTrabajos(): array
    {
        return $this->ordenTrabajo()->trabajos
            ->map(fn (Trabajo $trabajo) => $trabajo->estadoTablero())
            ->all();
    }

    private function ordenTrabajo(): OrdenTrabajo
    {
        /** @var OrdenTrabajo $ordenTrabajo */
        $ordenTrabajo = $this->route('ordenTrabajo');
        $ordenTrabajo->loadMissing('trabajos.sesiones', 'orden.categoriaInsumo');

        return $ordenTrabajo;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'motivo_correccion.required' => __('operaciones.ordenes_trabajo.error_motivo_correccion_requerido'),
        ];
    }
}
