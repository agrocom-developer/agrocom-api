<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\Excepciones\OrdenNoEditable;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Edición de una orden de aplicación (HU-25, tarea 38).
 *
 * Decisión de esta tarea: una orden solo se edita mientras está `emitida`.
 * Una vez `vigente` puede estar ya en el pull de catálogo de la app de campo
 * (`GET /api/sync/catalogo`, sin caché — ver `LecturaOrdenesVigentesEloquent`)
 * con el piloto operando sobre esos datos: cambiarlos desde el panel sin
 * ningún mecanismo que reabra la sincronización sería silenciosamente
 * peligroso. La restricción vive ACÁ (no solo ocultando el link "Editar" en la
 * vista — invariante 7), así un `PUT` directo la respeta igual. `estado` nunca
 * viaja en `$atributos`: el cambio de estado es responsabilidad exclusiva de
 * `MaquinaEstadosOrden`.
 *
 * Reforma 19/9/2026 (ADR 0022): el contrato, el número de aplicación y los
 * lotes NO se editan — la orden es la aplicación N de ESE contrato, con todos
 * sus lotes. Si se emitió sobre el contrato equivocado, se elimina y se emite
 * de nuevo. Solo se corrigen los datos de la aplicación (tipo, insumo, dosis,
 * equipos, contacto, fecha, observaciones). El estado se relee con la fila
 * bloqueada, para no editar una orden que otro operador acaba de activar.
 */
final class ActualizarOrden
{
    private const array ATRIBUTOS_EDITABLES = [
        'cantidad_equipos_necesarios',
        'tipo_aplicacion',
        'categoria_insumo_id',
        'kilos_por_vuelo',
        'litros_ha',
        'observaciones',
        'emitida_por_contacto_id',
        'fecha_emision',
    ];

    /**
     * @param  array<string, mixed>  $atributos  solo los datos editables; cualquier otra clave (contrato, número, estado) se ignora.
     *
     * @throws OrdenNoEditable si `$orden` no está `emitida`.
     */
    public function ejecutar(OrdenAplicacion $orden, array $atributos): OrdenAplicacion
    {
        return DB::transaction(function () use ($orden, $atributos): OrdenAplicacion {
            $actual = OrdenAplicacion::query()->lockForUpdate()->findOrFail($orden->id);

            if ($actual->estado !== EstadoOrdenAplicacion::Emitida) {
                throw OrdenNoEditable::porEstado($actual->estado->value);
            }

            $actual->fill(Arr::only($atributos, self::ATRIBUTOS_EDITABLES));
            $actual->save();

            return $actual->refresh();
        });
    }
}
