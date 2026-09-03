<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\Excepciones\OrdenNoEditable;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;

/**
 * Edición de una orden de aplicación (HU-25, tarea 38).
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
 */
final class ActualizarOrden
{
    /**
     * @param  array<string, mixed>  $atributos  sin `estado`.
     *
     * @throws OrdenNoEditable si `$orden` no está `emitida`.
     */
    public function ejecutar(OrdenAplicacion $orden, array $atributos): OrdenAplicacion
    {
        if ($orden->estado !== EstadoOrdenAplicacion::Emitida) {
            throw OrdenNoEditable::porEstado($orden->estado->value);
        }

        $orden->fill($atributos);
        $orden->save();

        return $orden->refresh();
    }
}
