<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Dominio\Excepciones\CultivoDuplicado;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cultivo;
use Illuminate\Database\QueryException;

/**
 * Alta de un cultivo (HU-48, tarea 71): catálogo simple, nombre único entre
 * cultivos activos (índice parcial `com_cultivos_nombre_unico`).
 */
final class CrearCultivo
{
    public function ejecutar(string $nombre, bool $activo): Cultivo
    {
        $cultivo = new Cultivo([
            'nombre' => $nombre,
            'activo' => $activo,
        ]);

        try {
            $cultivo->save();
        } catch (QueryException $excepcion) {
            $this->relanzarComoDuplicado($excepcion, $nombre);
        }

        return $cultivo->refresh();
    }

    /**
     * Mismo criterio que `CrearCliente`/`CrearPropiedad`: el formato del mensaje
     * difiere por driver (Postgres nombra el índice; SQLite nombra
     * tabla.columna).
     *
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
