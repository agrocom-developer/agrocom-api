<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\Excepciones\OrdenNoEditable;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use Illuminate\Support\Facades\DB;

/**
 * Edición de una orden de aplicación (HU-25, tarea 38; ampliada a N lotes
 * por HU-92, tarea 107).
 *
 * Decisión de esta tarea: una orden solo se edita mientras está `emitida`.
 * Una vez `vigente` puede estar ya en el pull de catálogo de la app de campo
 * (`GET /api/sync/catalogo`, sin caché — ver `LecturaOrdenesVigentesEloquent`)
 * con el piloto operando sobre esos parámetros de vuelo/clima: cambiarlos
 * desde el panel sin ningún mecanismo que reabra la sincronización sería
 * silenciosamente peligroso. La restricción vive ACÁ (no solo ocultando el
 * link "Editar" en la vista — invariante 7), así un `PUT` directo la respeta
 * igual. `estado` nunca viaja en `$atributos`: el cambio de estado es
 * responsabilidad exclusiva de `Aplicacion/ActivarOrden`.
 *
 * Mientras la orden es `emitida` no puede tener `Trabajo` alguno (eso solo
 * ocurre tras `activar()`, vía `AsignarEquiposOrden` o el motor de sync), así
 * que reemplazar la lista de lotes completa en cada edición es seguro: no
 * hay ningún `Trabajo` que pudiera quedar huérfano de su lote.
 */
final class ActualizarOrden
{
    /**
     * @param  array<string, mixed>  $atributos  sin `estado`.
     * @param  list<array{lote_id: int, hectareas_solicitadas: string}>  $lotes
     *
     * @throws OrdenNoEditable si `$orden` no está `emitida`.
     */
    public function ejecutar(OrdenAplicacion $orden, array $atributos, array $lotes): OrdenAplicacion
    {
        if ($orden->estado !== EstadoOrdenAplicacion::Emitida) {
            throw OrdenNoEditable::porEstado($orden->estado->value);
        }

        return DB::transaction(function () use ($orden, $atributos, $lotes): OrdenAplicacion {
            $orden->fill($atributos);
            $orden->save();

            $idsFinales = array_map(static fn (array $lote): int => $lote['lote_id'], $lotes);

            $orden->ordenLotes()->whereNotIn('lote_id', $idsFinales)->delete();

            foreach ($lotes as $lote) {
                $orden->ordenLotes()->updateOrCreate(
                    ['lote_id' => $lote['lote_id']],
                    ['hectareas_solicitadas' => $lote['hectareas_solicitadas']],
                );
            }

            return $orden->refresh();
        });
    }
}
