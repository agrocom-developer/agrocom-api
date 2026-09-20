<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Requests;

use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Http\Requests\Concerns\ValidaTandaDeTrabajo;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/trabajos` (reforma 18/9/2026, "Orden de Trabajo"): a
 * diferencia de `AsignarEquipoOrdenRequest` (route-bound a `{orden}`), acá la
 * orden de aplicación se elige DENTRO del formulario — `orden_id` viaja como
 * campo más, no como parámetro de ruta, porque `/panel/trabajos/crear` no
 * cuelga de una orden puntual (se puede llegar sin preseleccionar ninguna).
 *
 * Mismas reglas de `parametros`/`equipos[]` que la pantalla vieja, vía
 * {@see ValidaTandaDeTrabajo} — ambas construyen la misma forma para
 * `Aplicacion/CrearOrdenTrabajo::ejecutar()`.
 */
final class CrearOrdenTrabajoRequest extends FormRequest
{
    use ValidaTandaDeTrabajo;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'orden_id' => [
                'required',
                'integer',
                Rule::exists('ope_ordenes_aplicacion', 'id')
                    ->where('estado', EstadoOrdenAplicacion::Vigente->value)
                    ->whereNull('deleted_at'),
            ],
            ...$this->reglasParametrosYEquipos($this->integer('orden_id') ?: null),
        ];
    }

    /**
     * El formulario dibuja tantos bloques de equipo como definió la orden
     * (`cantidad_equipos_necesarios`), pero una tanda puede salir con menos
     * —"al menos uno"—: un bloque que llega ENTERO en blanco (sin escuadra y
     * sin ningún dato de lote) no es un error, es un equipo que esta tanda no
     * usa, y se descarta acá. El primero nunca se descarta: si viene vacío,
     * que responda la validación normal con sus mensajes.
     */
    protected function prepareForValidation(): void
    {
        $this->descartarCaldaSinMarcar();

        $equipos = $this->input('equipos');

        if (! is_array($equipos)) {
            return;
        }

        $primero = array_key_first($equipos);

        $conDatos = array_filter(
            $equipos,
            fn (mixed $equipo, int|string $indice): bool => $indice === $primero || ! $this->bloqueEnBlanco($equipo),
            ARRAY_FILTER_USE_BOTH,
        );

        $this->merge(['equipos' => array_values($conDatos)]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $ordenId = $this->integer('orden_id');
            $orden = $ordenId !== 0 ? OrdenAplicacion::query()->find($ordenId) : null;

            $this->validarParametrosYEquipos($validator, $orden);

            $equipos = (array) $this->input('equipos', []);

            if ($orden !== null && count($equipos) > max(1, (int) $orden->cantidad_equipos_necesarios)) {
                $validator->errors()->add('equipos', __('operaciones.ordenes_trabajo.error_equipos_superan_orden', [
                    'cantidad' => max(1, (int) $orden->cantidad_equipos_necesarios),
                ]));
            }
        });
    }

    /**
     * La calda llega como casillas: un producto sin marcar no debería enviar
     * nada (sus campos van `disabled`), pero si igual llega su unidad o su
     * cantidad sin el `producto`, ese renglón no existe.
     */
    private function descartarCaldaSinMarcar(): void
    {
        $parametros = $this->input('parametros');

        if (! is_array($parametros) || ! is_array($parametros['calda'] ?? null)) {
            return;
        }

        // Sin reindexar: la clave del producto (`glifosato`, `agua`…) es la que
        // la vista usa para volver a marcar la casilla tras un error.
        $parametros['calda'] = array_filter(
            $parametros['calda'],
            fn (mixed $renglon): bool => is_array($renglon) && ($renglon['producto'] ?? null) !== null && $renglon['producto'] !== '',
        );

        $this->merge(['parametros' => $parametros]);
    }

    private function bloqueEnBlanco(mixed $equipo): bool
    {
        if (! is_array($equipo)) {
            return true;
        }

        if (($equipo['equipo_trabajo_id'] ?? null) !== null && $equipo['equipo_trabajo_id'] !== '') {
            return false;
        }

        foreach ((array) ($equipo['lotes'] ?? []) as $lote) {
            foreach ((array) $lote as $valor) {
                if ($valor !== null && $valor !== '') {
                    return false;
                }
            }
        }

        return true;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'orden_id.required' => __('operaciones.ordenes_trabajo.error_orden_requerida'),
            'orden_id.exists' => __('operaciones.ordenes_trabajo.error_orden_no_vigente'),
            'equipos.required' => __('operaciones.asignacion_equipos.error_equipos_requerido'),
            'equipos.*.equipo_trabajo_id.required' => __('operaciones.ordenes_trabajo.error_escuadra_requerida'),
            'equipos.*.equipo_trabajo_id.distinct' => __('operaciones.ordenes_trabajo.error_escuadra_repetida'),
            'equipos.*.lotes.required' => __('operaciones.asignacion_equipos.error_lotes_requerido'),
            'equipos.*.lotes.*.lote_id.required' => __('operaciones.asignacion_equipos.error_lote_requerido'),
            'equipos.*.lotes.*.hectareas.required' => __('operaciones.asignacion_equipos.error_hectareas_requerido'),
            'equipos.*.lotes.*.turno.required' => __('operaciones.asignacion_equipos.error_turno_requerido'),
            'equipos.*.lotes.*.turno_hora_inicio.required' => __('operaciones.asignacion_equipos.error_turno_hora_requerida'),
            'equipos.*.lotes.*.turno_hora_fin.required' => __('operaciones.asignacion_equipos.error_turno_hora_requerida'),
        ];
    }
}
