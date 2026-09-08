<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Aplicacion\Lote\GuardadoLote;
use App\Dominios\Comercial\Dominio\Excepciones\LoteDuplicado;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;

/**
 * Alta de un lote suelto, desde su propia ficha (tarea 77, HU-54, etapa 2) —
 * no confundir con el alta de lotes que trae `CrearCampo` cuando una
 * propiedad se da de alta con los suyos en la misma operación.
 *
 * Mismo caso de uso de guardado que `CrearCampo`/`ActualizarCampo`, vía
 * {@see GuardadoLote}: no hay dos formas de crear un lote, solo dos puntos de
 * entrada (formulario de propiedad, formulario de lote) que llaman al mismo
 * colaborador — prompt de la tarea, punto "Qué NO hacer".
 */
final class CrearLote
{
    /** @param  array{codigo: string, hectareas: string, geometria: array<string, mixed>|null, restricciones: string|null}  $datos
     *
     * @throws LoteDuplicado si el código ya pertenece a otro lote activo de la misma propiedad.
     */
    public function ejecutar(int $campoId, array $datos): Lote
    {
        $campo = Campo::query()->findOrFail($campoId);

        return GuardadoLote::guardar($campo->lotes()->make(), $datos);
    }
}
