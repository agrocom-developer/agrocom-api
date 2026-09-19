<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Requests;

/**
 * `POST /panel/propiedades/{propiedad}/lotes/generar` (HU-72 reconstruida,
 * 16/9/2026). La autorización (`comercial.lote.crear`) se verifica en el
 * controlador, contra el rol activo — no acá, mismo criterio que el resto
 * del panel.
 *
 * `hectareas` y `terreno.*` son UN SOLO juego de valores que se aplica a los
 * `cantidad` lotes generados por igual (16/9/2026, pedido directo: no una
 * fila por lote). Las hectáreas se piden desde el 19/9/2026 — antes nacían
 * todas con 1 ha y había que corregirlas lote por lote. Sin
 * `codigo`/`geometria`: el código lo arma `CrearLotesMasivo` con `prefijo` +
 * numeración correlativa, y el polígono se dibuja después.
 *
 * SIN `cultivo_id`/`campania_id` a propósito (corregido tras confundir
 * los dos conceptos): esta pantalla crea ESTRUCTURA, no siembra — el
 * cultivo se asigna después desde la ficha del lote o desde
 * `propiedades/siembra` (`GuardarSiembraCampania`), nunca acá.
 */
final class GenerarLotesRequest extends LotesBloqueRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'prefijo' => ['required', 'string', 'max:30'],
            'cantidad' => ['required', 'integer', 'min:1', 'max:50'],
            'hectareas' => $this->reglasHectareas(requeridas: true),
            ...$this->reglasTerreno(),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'prefijo.required' => __('comercial.validacion.lotes_generar_prefijo_requerido'),
            'cantidad.required' => __('comercial.validacion.lotes_generar_cantidad_requerida'),
            'cantidad.min' => __('comercial.validacion.lotes_generar_cantidad_minima'),
            'cantidad.max' => __('comercial.validacion.lotes_generar_cantidad_maxima'),
            ...$this->mensajesBloque(),
        ];
    }
}
