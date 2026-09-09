<?php

namespace App\Dominios\Finanzas\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/combustible` (HU-35, tarea 49; reescrito por la tarea 73,
 * HU-50). La autorización (permiso `finanzas.combustible.crear`) se
 * verifica en el controlador, contra el rol activo — no acá, mismo criterio
 * que `CrearGastoRequest`.
 *
 * `litros`/`monto` > 0 replican los `CHECK` de la migración de creación —
 * así el usuario ve un error de validación de Laravel, nunca el
 * `QueryException` crudo de Postgres.
 *
 * `equipo_trabajo_id` es OBLIGATORIA (a diferencia de la de `fin_gastos`,
 * nullable): el combustible siempre lo consume una cuadrilla. Solo valida
 * que exista — que el RECURSO elegido perteneciera a ESE equipo en la fecha
 * de la carga es una guarda de negocio y vive en `Aplicacion/CrearCombustible`
 * (necesita leer la vigencia vía `Personal\Contratos\LecturaEquipoTrabajo`,
 * no algo que una regla de `Rule::exists` pueda expresar).
 *
 * `recurso` viaja como un único campo compuesto, no como `recurso_tipo` +
 * `recurso_id` sueltos: el `<select>` de la vista ya ofrece, para el equipo
 * y la fecha elegidos, solo
 * los recursos vigentes de ESE equipo (server-side, vía
 * `CombustibleController::create()`) — el valor viaja compuesto
 * `"{tipo}:{id}"` porque el mismo `id` numérico puede repetirse entre
 * `man_vehiculos`/`man_generadores`/`ope_drones` (tablas independientes). El
 * controlador lo separa en `recurso_tipo`/`recurso_id` antes de llamar al
 * caso de uso — acá solo se valida la FORMA.
 *
 * `campania_id` (ADR 0015 punto 6) es OPCIONAL, mismo criterio que en
 * `CrearGastoRequest`: vacío es consumo interno que no pertenece a ninguna
 * campaña.
 */
final class CrearCombustibleRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date'],
            'base_id' => ['required', 'integer', Rule::exists('per_bases', 'id')->whereNull('deleted_at')],
            'equipo_trabajo_id' => ['required', 'integer', Rule::exists('per_equipos_trabajo', 'id')->whereNull('deleted_at')],
            'campania_id' => ['nullable', 'integer', Rule::exists('cpn_campanias', 'id')->whereNull('deleted_at')],
            'recurso' => ['required', 'regex:/^(dron|vehiculo|generador):\d+$/'],
            'litros' => ['required', 'numeric', 'gt:0'],
            'monto' => ['required', 'numeric', 'gt:0'],
            'descripcion' => ['nullable', 'string', 'max:200'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'base_id.required' => __('finanzas.combustible.error_base_requerida'),
            'base_id.exists' => __('finanzas.combustible.error_base_invalida'),
            'equipo_trabajo_id.required' => __('finanzas.combustible.error_equipo_requerido'),
            'equipo_trabajo_id.exists' => __('finanzas.combustible.error_equipo_invalido'),
            'recurso.required' => __('finanzas.combustible.error_recurso_requerido'),
            'recurso.regex' => __('finanzas.combustible.error_recurso_invalido'),
        ];
    }
}
