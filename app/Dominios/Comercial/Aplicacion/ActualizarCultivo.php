<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Dominio\Excepciones\CultivoDuplicado;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cultivo;
use Illuminate\Database\QueryException;

/**
 * Edición de un cultivo (HU-48, tarea 71). Mismas reglas que `CrearCultivo`.
 */
final class ActualizarCultivo
{
    public function ejecutar(Cultivo $cultivo, string $nombre, bool $activo): Cultivo
    {
        $cultivo->nombre = $nombre;
        $cultivo->activo = $activo;

        try {
            $cultivo->save();
        } catch (QueryException $excepcion) {
            $this->relanzarComoDuplicado($excepcion, $nombre);
        }

        return $cultivo->refresh();
    }

    /**
     * @throws CultivoDuplicado si la violación corresponde al nombre.
     * @throws QueryException si la violación no es la contemplada.
     */
    private function relanzarComoDuplicado(QueryException $excepcion, string $nombre): never
    {
        $mensaje = $excepcion->getMessage();

        if (str_contains($mensaje, 'com_cultivos_nombre_unico') || str_contains($mensaje, 'com_cultivos.nombre')) {
            throw CultivoDuplicado::porNombre($nombre);
        }

        throw $excepcion;
    }
}
