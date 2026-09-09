<?php

namespace App\Dominios\Comercial\Aplicacion\Lote;

use App\Dominios\Comercial\Dominio\Excepciones\LoteDuplicado;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use Illuminate\Database\QueryException;

/**
 * Guardado de un lote con traducción del índice único parcial
 * `com_lotes_codigo_unico` a {@see LoteDuplicado} (tarea 77, HU-54).
 *
 * Colaborador compartido: antes de esta tarea, `CrearCampo` y `ActualizarCampo`
 * tenían cada uno su propia copia de este mismo try/catch. Se extrae acá para
 * que los casos de uso de lote suelto (`CrearLote`/`ActualizarLote`, ficha
 * propia) lo reusen sin duplicarlo de nuevo — mismo caso de uso de guardado,
 * lo llame quien lo llame (prompt de la tarea, punto "Qué NO hacer").
 */
final class GuardadoLote
{
    /**
     * @param  array{codigo: string, hectareas: string, geometria: array<string, mixed>|null, restricciones: string|null}  $datos
     *
     * @throws LoteDuplicado si el código ya pertenece a otro lote activo del mismo campo.
     */
    public static function guardar(Lote $lote, array $datos): Lote
    {
        $lote->fill($datos);

        try {
            $lote->save();
        } catch (QueryException $excepcion) {
            self::relanzarComoDuplicado($excepcion, $datos['codigo']);
        }

        return $lote;
    }

    /**
     * @throws LoteDuplicado si la violación corresponde al código.
     * @throws QueryException si la violación no es la contemplada.
     */
    private static function relanzarComoDuplicado(QueryException $excepcion, string $codigo): never
    {
        $mensaje = $excepcion->getMessage();

        if (str_contains($mensaje, 'com_lotes_codigo_unico') || str_contains($mensaje, 'com_lotes.codigo')) {
            throw LoteDuplicado::porCodigo($codigo);
        }

        throw $excepcion;
    }
}
