<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Aplicacion\Lote\GuardadoLote;
use App\Dominios\Comercial\Dominio\Excepciones\LoteDuplicado;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;

/**
 * Edición de un lote suelto, desde su propia ficha (tarea 77, HU-54, etapa
 * 2). Admite reasignar la propiedad (`campo_id`): la ficha del lote trae su
 * propio select de propiedad, a diferencia del formulario de propiedad
 * (`ActualizarCampo`) donde el campo padre ya está fijo.
 *
 * Mismo caso de uso de guardado que `CrearCampo`/`ActualizarCampo`, vía
 * {@see GuardadoLote}.
 */
final class ActualizarLote
{
    /** @param  array{codigo: string, hectareas: string, geometria: array<string, mixed>|null, restricciones: string|null}  $datos
     *
     * @throws LoteDuplicado si el código ya pertenece a otro lote activo de la propiedad de destino.
     */
    public function ejecutar(Lote $lote, int $campoId, array $datos): Lote
    {
        $lote->campo_id = $campoId;

        return GuardadoLote::guardar($lote, $datos);
    }
}
