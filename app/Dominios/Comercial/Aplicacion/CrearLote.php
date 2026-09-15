<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Aplicacion\Lote\GuardadoLote;
use App\Dominios\Comercial\Dominio\Excepciones\LoteDuplicado;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;

/**
 * Alta de un lote, desde su propia ficha (tarea 77, HU-54, etapa 2; ADR
 * 0020 — flujo separado del de propiedad, la propiedad nunca trae lotes
 * en su propio formulario).
 *
 * Mismo caso de uso de guardado que `ActualizarLote`, vía
 * {@see GuardadoLote}: no hay dos formas de crear un lote, un único
 * colaborador que ambos reusan.
 */
final class CrearLote
{
    /** @param  array{codigo: string, hectareas: string, geometria: array<string, mixed>|null, restricciones: string|null, desnivel: string|null, limpieza: string|null}  $datos
     *
     * @throws LoteDuplicado si el código ya pertenece a otro lote activo de la misma propiedad.
     */
    public function ejecutar(int $propiedadId, array $datos): Lote
    {
        $propiedad = Propiedad::query()->findOrFail($propiedadId);

        return GuardadoLote::guardar($propiedad->lotes()->make(), $datos);
    }
}
