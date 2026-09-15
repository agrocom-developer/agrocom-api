<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Dominio\Excepciones\PropiedadDuplicada;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use Illuminate\Database\QueryException;

/**
 * Edición de una propiedad (ADR 0020). Mismas reglas que `CrearPropiedad`.
 */
final class ActualizarPropiedad
{
    /**
     * @param  array<string, mixed>|null  $geometria
     *
     * @throws PropiedadDuplicada si el nombre ya pertenece a otra propiedad
     *                            activa del mismo cliente.
     */
    public function ejecutar(
        Propiedad $propiedad,
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
        $propiedad->cliente_id = $clienteId;
        $propiedad->nombre = $nombre;
        $propiedad->ubicacion = $ubicacion;
        $propiedad->departamento = $departamento;
        $propiedad->municipio = $municipio;
        $propiedad->localidad = $localidad;
        $propiedad->latitud = $latitud;
        $propiedad->longitud = $longitud;
        $propiedad->geometria = $geometria;

        try {
            $propiedad->save();
        } catch (QueryException $excepcion) {
            $this->relanzarComoDuplicada($excepcion, $nombre);
        }

        return $propiedad->refresh();
    }

    /**
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
