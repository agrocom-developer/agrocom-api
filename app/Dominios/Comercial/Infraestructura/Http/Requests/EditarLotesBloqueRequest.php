<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Requests;

use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use Illuminate\Validation\Rule;

/**
 * `PUT /panel/propiedades/{propiedad}/lotes/bloque` (19/9/2026): editar en
 * bloque los lotes que ya existen. La autorización (`comercial.lote.editar`,
 * más `.crear`/`.eliminar` según la cantidad pedida) se verifica en el
 * controlador, contra el rol activo.
 *
 * `cantidad` es el TOTAL de lotes que debe tener la propiedad al terminar:
 * más que hoy crea los que faltan (hasta `LOTES_MAXIMOS_POR_TANDA` de una vez, mismo tope que
 * generar), menos quita los últimos, y nunca menos de uno — dar de baja todos
 * los lotes se hace desde el listado, no por un número en un formulario.
 *
 * `hectareas` es opcional: vacío deja las de cada lote como están (ver
 * `EditarLotesEnBloque`); si hay que crear lotes lo exige el caso de uso, que
 * es quien sabe cuántos hay. `prefijo` solo hace falta para los lotes nuevos.
 */
final class EditarLotesBloqueRequest extends LotesBloqueRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $actuales = $this->lotesActuales();

        return [
            'prefijo' => [
                Rule::requiredIf(fn () => (int) $this->input('cantidad') > $actuales),
                'nullable',
                'string',
                'max:30',
            ],
            'cantidad' => ['required', 'integer', 'min:1', 'max:'.($actuales + self::LOTES_MAXIMOS_POR_TANDA)],
            'hectareas' => $this->reglasHectareas(requeridas: false),
            ...$this->reglasTerreno(),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'prefijo.required' => __('comercial.validacion.lotes_generar_prefijo_requerido'),
            'cantidad.required' => __('comercial.validacion.lotes_bloque_cantidad_requerida'),
            'cantidad.min' => __('comercial.validacion.lotes_bloque_cantidad_minima'),
            'cantidad.max' => __('comercial.validacion.lotes_bloque_cantidad_maxima', ['maximo' => self::LOTES_MAXIMOS_POR_TANDA]),
            ...$this->mensajesBloque(),
        ];
    }

    private function lotesActuales(): int
    {
        $propiedad = $this->route('propiedad');

        return $propiedad instanceof Propiedad ? $propiedad->lotes()->count() : 0;
    }
}
