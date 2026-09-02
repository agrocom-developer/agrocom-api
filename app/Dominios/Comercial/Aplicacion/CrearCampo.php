<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Dominio\Excepciones\CampoDuplicado;
use App\Dominios\Comercial\Dominio\Excepciones\LoteDuplicado;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Alta de un campo con sus lotes en una sola operación (HU-24, tarea 35):
 * mismo criterio que `CrearCliente` con sus contactos — el formulario es uno
 * solo, así que el campo y sus lotes nacen en la misma transacción.
 */
final class CrearCampo
{
    /**
     * @param  list<array{codigo: string, hectareas: string, geometria: array<string, mixed>|null, restricciones: string|null}>  $lotes
     *
     * @throws CampoDuplicado si el nombre ya pertenece a otro campo activo
     *                        del mismo cliente (índice parcial `com_campos_nombre_unico`).
     * @throws LoteDuplicado si el código de un lote ya pertenece a otro lote
     *                       activo del mismo campo (índice parcial `com_lotes_codigo_unico`).
     */
    public function ejecutar(int $clienteId, string $nombre, ?string $ubicacion, array $lotes): Campo
    {
        return DB::transaction(function () use ($clienteId, $nombre, $ubicacion, $lotes): Campo {
            $campo = new Campo([
                'cliente_id' => $clienteId,
                'nombre' => $nombre,
                'ubicacion' => $ubicacion,
            ]);

            try {
                $campo->save();
            } catch (QueryException $excepcion) {
                $this->relanzarCampoComoDuplicado($excepcion, $nombre);
            }

            foreach ($lotes as $datos) {
                try {
                    $campo->lotes()->create($datos);
                } catch (QueryException $excepcion) {
                    $this->relanzarLoteComoDuplicado($excepcion, $datos['codigo']);
                }
            }

            return $campo->refresh();
        });
    }

    /**
     * @throws CampoDuplicado si la violación corresponde al nombre.
     * @throws QueryException si la violación no es la contemplada.
     */
    private function relanzarCampoComoDuplicado(QueryException $excepcion, string $nombre): never
    {
        $mensaje = $excepcion->getMessage();

        if (str_contains($mensaje, 'com_campos_nombre_unico') || str_contains($mensaje, 'com_campos.nombre')) {
            throw CampoDuplicado::porNombre($nombre);
        }

        throw $excepcion;
    }

    /**
     * @throws LoteDuplicado si la violación corresponde al código.
     * @throws QueryException si la violación no es la contemplada.
     */
    private function relanzarLoteComoDuplicado(QueryException $excepcion, string $codigo): never
    {
        $mensaje = $excepcion->getMessage();

        if (str_contains($mensaje, 'com_lotes_codigo_unico') || str_contains($mensaje, 'com_lotes.codigo')) {
            throw LoteDuplicado::porCodigo($codigo);
        }

        throw $excepcion;
    }
}
