<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Dominio\Excepciones\PropiedadDuplicada;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use Illuminate\Database\QueryException;

/**
 * Alta de una propiedad (ADR 0020): nivel de terreno entre `Cliente` y
 * `Lote`. Sin sub-entidad propia en esta operación (a diferencia de
 * `CrearCliente`, que trae sus contactos en la misma transacción): los
 * lotes de una propiedad se cargan por su propia pantalla
 * (`LotesController`), no acá — `geometria` es un atributo propio de la
 * propiedad (los terrenos que la componen), no una sub-entidad.
 */
final class CrearPropiedad
{
    /**
     * @param  array<string, mixed>|null  $geometria
     *
     * @throws PropiedadDuplicada si el nombre ya pertenece a otra propiedad
     *                            activa del mismo cliente (índice parcial
     *                            `com_propiedades_nombre_unico`).
     */
    public function ejecutar(
        int $clienteId,
        string $nombre,
        ?string $ubicacion,
        ?string $departamento,
        ?string $municipio,
        ?string $localidad,
        ?string $latitud,
        ?string $longitud,
        ?array $geometria = null,
    ): Propiedad {
        $propiedad = new Propiedad([
            'cliente_id' => $clienteId,
            'nombre' => $nombre,
            'ubicacion' => $ubicacion,
            'departamento' => $departamento,
            'municipio' => $municipio,
            'localidad' => $localidad,
            'latitud' => $latitud,
            'longitud' => $longitud,
            'geometria' => $geometria,
        ]);

        try {
            $propiedad->save();
        } catch (QueryException $excepcion) {
            $this->relanzarComoDuplicada($excepcion, $nombre);
        }

        return $propiedad->refresh();
    }

    /**
     * Mismo criterio que `CrearCliente`: el formato del mensaje
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
