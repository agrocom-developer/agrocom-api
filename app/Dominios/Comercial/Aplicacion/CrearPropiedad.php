<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Dominio\Excepciones\PropiedadDuplicada;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use Illuminate\Database\QueryException;

/**
 * Alta de una propiedad (ADR 0018): nivel de terreno entre `Cliente` y
 * `Campo`. Sin sub-entidad propia en esta operación (a diferencia de
 * `CrearCliente`/`CrearCampo`, que traen contactos/lotes en la misma
 * transacción): los campos de una propiedad se cargan por su propia pantalla
 * (`CamposController`), no acá.
 */
final class CrearPropiedad
{
    /**
     * @throws PropiedadDuplicada si el nombre ya pertenece a otra propiedad
     *                            activa del mismo cliente (índice parcial
     *                            `com_propiedades_nombre_unico`).
     */
    public function ejecutar(int $clienteId, string $nombre, ?string $ubicacion): Propiedad
    {
        $propiedad = new Propiedad([
            'cliente_id' => $clienteId,
            'nombre' => $nombre,
            'ubicacion' => $ubicacion,
        ]);

        try {
            $propiedad->save();
        } catch (QueryException $excepcion) {
            $this->relanzarComoDuplicada($excepcion, $nombre);
        }

        return $propiedad->refresh();
    }

    /**
     * Mismo criterio que `CrearCliente`/`CrearCampo`: el formato del mensaje
     * difiere por driver (Postgres nombra el índice; SQLite, motor de los
     * tests, nombra tabla.columna).
     *
     * @throws PropiedadDuplicada si la violación corresponde al nombre.
     * @throws QueryException si la violación no es la contemplada.
     */
    private function relanzarComoDuplicada(QueryException $excepcion, string $nombre): never
    {
        $mensaje = $excepcion->getMessage();

        if (str_contains($mensaje, 'com_propiedades_nombre_unico') || str_contains($mensaje, 'com_propiedades.nombre')) {
            throw PropiedadDuplicada::porNombre($nombre);
        }

        throw $excepcion;
    }
}
